<?php

namespace App\Poster\Listeners;

use App\Poster\Events\Product\PosterProductActionPerformed;
use App\Poster\Facades\PosterStore;
use App\Poster\Models\PosterCategory;
use App\Poster\Models\PosterProduct;
use App\Poster\Models\PosterProductModification;
use App\Poster\Transformers\PosterCategoryAsSalesboxCategory;
use App\Poster\Transformers\PosterProductAsSalesboxOffer;
use App\Poster\Transformers\PosterProductModificationAsSalesboxOffer;
use App\Salesbox\Facades\SalesboxApi;
use App\Salesbox\Facades\SalesboxStore;
use App\Salesbox\Models\SalesboxOfferV4;
use Illuminate\Http\Request;
use function collect;

class PosterProductSyncWithSalesbox
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(PosterProductActionPerformed $event): bool
    {
        if ($event->isAdded() || $event->isRestored() || $event->isChanged()) {
            SalesboxStore::authenticate();
            SalesboxStore::loadCategories();
            SalesboxStore::loadOffers();

            if(!PosterStore::isCategoriesLoaded()) {
                PosterStore::loadCategories();
            }

            if(!PosterStore::isProductsLoaded()) {
                PosterStore::loadProducts();
            }

            if (!PosterStore::productExists($event->getObjectId())) {
                throw new \RuntimeException(sprintf('product#%s is not found in poster', $event->getObjectId()));
            }

            $poster_product = PosterStore::findProduct($event->getObjectId());

            $product_create_ids = [];
            $product_update_ids = [];
            $category_create_ids = [];

            if (!SalesboxStore::offerExistsWithExternalId($event->getObjectId())) {
                $product_create_ids[] = $event->getObjectId();
            } else {
                $product_update_ids[] = $event->getObjectId();
            }


            if(!$poster_product->belongsToRootCategory()) {
                if (!SalesboxStore::categoryExistsWithExternalId($poster_product->getMenuCategoryId())) {
                    $category_create_ids[] = $poster_product->getMenuCategoryId();
                }

                $poster_category = PosterStore::findCategory($poster_product->getMenuCategoryId());

                if ($poster_category->hasParentCategory()) {
                    $parent_poster_categories = PosterStore::getCategoryParents($poster_category);

                    foreach ($parent_poster_categories as $parent_poster_category) {
                        if (!SalesboxStore::categoryExistsWithExternalId($parent_poster_category->getCategoryId())) {
                            $category_create_ids[] = $parent_poster_category->getCategoryId();
                        }
                    }
                }
            }


            if (count($category_create_ids) > 0) {
                // $this->createCategories($category_create_ids);
                // SalesboxStore::loadCategories();
            }

            if (count($product_create_ids) > 0) {
                $this->createSingleOffers($product_create_ids);
                $this->createMultipleOffers($product_create_ids);
            }

            if (count($product_update_ids) > 0) {
                $this->updateSingleOffers($product_update_ids);
                $this->updateMultipleOffers($product_update_ids);
            }

        }


        return true;
    }

    public function createCategories(array $ids) {
        $found_poster_categories = PosterStore::findCategory($ids);

        $poster_categories_as_salesbox_ones = array_map(function(PosterCategory $posterCategory) {
            $transformer = new PosterCategoryAsSalesboxCategory($posterCategory);
            return $transformer->transform();
        }, $found_poster_categories);
        SalesboxStore::createManyCategories($poster_categories_as_salesbox_ones);
    }

    public function createSingleOffers(array $ids) {
        // handle products without modifications
        $poster_products_as_salesbox_offers = array_map(
            function(PosterProduct $posterProduct) {
                $transformer = new PosterProductAsSalesboxOffer($posterProduct);
                return $transformer->transform();
            },
            PosterStore::findProductsWithoutModifications($ids)
        );

        if (count($poster_products_as_salesbox_offers) > 0) {
            SalesboxStore::createManyOffers($poster_products_as_salesbox_offers);
        }
    }

    public function updateSingleOffers(array $ids) {
        // handle products without modifications

        $poster_products_as_salesbox_offers = array_map(
            function(PosterProduct $posterProduct) {
                $offer = SalesboxStore::findOfferByExternalId($posterProduct->getProductId());
                $transformer = new PosterProductAsSalesboxOffer($posterProduct);
                return $transformer->updateFrom($offer);
            },
            PosterStore::findProductsWithoutModifications($ids)
        );

        if (count($poster_products_as_salesbox_offers) > 0) {
            $offersAsArray = array_map(function (SalesboxOfferV4 $offer) {
                return [
                    'id' => $offer->getId(),
                    'categories' => $offer->getCategories(),
                    'available' => $offer->getAvailable(),
                    'price' => $offer->getPrice(),
                ];
            }, $poster_products_as_salesbox_offers);

            SalesboxApi::updateManyOffers([
                'offers' => array_values($offersAsArray) // reindex array, it's important, otherwise salesbox api will fail
            ]);
        }
    }

    public function createMultipleOffers(array $ids) {
        // handle products with modifications
        $modificatons_as_salesbox_offers = collect(
            PosterStore::findProductsWithModifications($ids)
        )
            ->map(function (PosterProduct $posterProduct) {
                return collect($posterProduct->getProductModifications())
                    ->map(function (PosterProductModification $modification) {
                        $transformer = new PosterProductModificationAsSalesboxOffer($modification);
                        return $transformer->transform();
                    });
            })
            ->flatten()
            ->toArray();

        if (count($modificatons_as_salesbox_offers) > 0) {
            SalesboxStore::createManyOffers($modificatons_as_salesbox_offers);
        }
    }

    public function updateMultipleOffers(array $ids) {
        // handle products with modifications
        /**
         * @var PosterProductModification[] $products_modifications
         */
        $products_modifications = array_merge(...array_map(function (PosterProduct $posterProduct) {
            return $posterProduct->getProductModifications();
        }, PosterStore::findProductsWithModifications($ids)));

        $salesbox_offers = SalesboxStore::findOfferByExternalId($ids);

        /**
         * @var SalesboxOfferV4[] $delete_salesbox_offers
         */
        $delete_salesbox_offers = [];
        /**
         * @var SalesboxOfferV4[] $delete_salesbox_offers
         */
        $create_salesbox_offers = [];
        /**
         * @var SalesboxOfferV4[] $delete_salesbox_offers
         */
        $update_salesbox_offers = [];

        foreach ($salesbox_offers as $offer) {
            if ($offer->hasModifierId()) {
                $poster_product = PosterStore::findProduct($offer->getExternalId());

                if (!$poster_product->hasModification($offer->getModifierId())) {
                    $delete_salesbox_offers[] = $offer;
                }
            }
        }

        foreach ($products_modifications as $modification) {
            $offer = SalesboxStore::findOfferByExternalId(
                $modification->getProduct()->getProductId(),
                $modification->getModificatorId()
            );
            if (!$offer) {
                $transformer = new PosterProductModificationAsSalesboxOffer($modification);
                $create_salesbox_offers[] = $transformer->transform();
            } else {
                $transformer = new PosterProductModificationAsSalesboxOffer($modification);
                $update_salesbox_offers[] = $transformer->updateFrom($offer);
            }
        }

        if(count($create_salesbox_offers)) {
            SalesboxStore::createManyOffers($create_salesbox_offers);
        }

        if (count($delete_salesbox_offers) > 0) {
            SalesboxStore::deleteManyOffers($delete_salesbox_offers);
        }

        if (count($update_salesbox_offers) > 0) {
            $offersAsArray = array_map(function (SalesboxOfferV4 $offer) {
                return [
                    'id' => $offer->getId(),
                    // 'categories' => $offer->getCategories(),
                    //'available' => $offer->getAvailable(),
                    'price' => $offer->getPrice(),
                ];
            }, $update_salesbox_offers);

            SalesboxApi::updateManyOffers([
                'offers' => array_values($offersAsArray) // reindex array, it's important, otherwise salesbox api will fail
            ]);
        }
    }


}
