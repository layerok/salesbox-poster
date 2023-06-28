<?php

namespace App\Poster\Listeners;

use App\Poster\Events\Dish\PosterDishActionPerformed;
use App\Poster\Facades\PosterStore;
use App\Poster\Models\PosterCategory;
use App\Poster\Models\PosterDishModification;
use App\Poster\Models\PosterDishModificationGroup;
use App\Poster\Models\PosterProduct;
use App\Poster\Transformers\PosterCategoryAsSalesboxCategory;
use App\Poster\Transformers\PosterDishModificationAsSalesboxOffer;
use App\Poster\Transformers\PosterProductAsSalesboxOffer;
use App\Salesbox\Facades\SalesboxApi;
use App\Salesbox\Facades\SalesboxStore;
use App\Salesbox\Models\SalesboxOfferV4;
use Illuminate\Http\Request;
use function collect;

class PosterDishSyncWithSalesbox
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(PosterDishActionPerformed $event): bool
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
                $this->createSingleCategories($category_create_ids);
                SalesboxStore::loadCategories();
            }

            if (count($product_create_ids) > 0) {
                $this->createSingleOffers($product_create_ids);
                // $this->createMultipleOffers($product_create_ids);

            }

            if (count($product_update_ids) > 0) {
                $this->updateSingleOffers($product_update_ids);
                // $this->updateMultipleOffers($product_update_ids);
            }

        }

        return true;
    }

    public function createSingleCategories($ids = [])
    {
        $poster_categories_as_salesbox_ones = array_map(function(PosterCategory $posterCategory) {
            $transformer = new PosterCategoryAsSalesboxCategory($posterCategory);
            return $transformer->transform();
        }, PosterStore::findCategory($ids));
        SalesboxStore::createManyCategories($poster_categories_as_salesbox_ones);
    }

    public function createSingleOffers($ids = [])
    {
        // handle products without modifications
        $poster_products_as_salesbox_offers = array_map(function(PosterProduct $posterProduct) {
            $transformer = new PosterProductAsSalesboxOffer($posterProduct);
            return $transformer->transform();
        }, PosterStore::findProductsWithoutModificationGroups($ids));

        if (count($poster_products_as_salesbox_offers) > 0) {
            SalesboxStore::createManyOffers($poster_products_as_salesbox_offers);
        }

    }

    public function updateSingleOffers($ids = [])
    {
        // handle products without modifications
        $poster_products_as_salesbox_offers = array_map(function(PosterProduct $posterProduct) {
            $offer = SalesboxStore::findOfferByExternalId($posterProduct->getProductId());
            $transformer = new PosterProductAsSalesboxOffer($posterProduct);
            return $transformer->updateFrom($offer);
        }, PosterStore::findProductsWithoutModifications($ids));

        if (count($poster_products_as_salesbox_offers) > 0) {
            $offersAsArray = array_map(function (SalesboxOfferV4 $offer) {
                return [
                    'id' => $offer->getId(),
                    'categories' => $offer->getCategories(),
                    //'available' => $offer->getAvailable(),
                    'price' => $offer->getPrice(),
                ];
            }, $poster_products_as_salesbox_offers);

            SalesboxApi::updateManyOffers([
                'offers' => array_values($offersAsArray) // reindex array, it's important, otherwise salesbox api will fail
            ]);
        }
    }


    public function createMultipleOffers($ids = [])
    {
        $group_modificatons_as_salesbox_offers = collect(
            PosterStore::findProductsWithModificationGroups($ids)
        )
            ->map(function (PosterProduct $posterProduct) {
                return collect($posterProduct->getDishModificationGroups())
                    ->filter(function (PosterDishModificationGroup $modification) {
                        // skip 'multiple' type modifications
                        // because I don't know how to store them in salesbox
                        // I doubt it is even possible
                        return $modification->isSingleType();
                    })
                    ->map(function (PosterDishModificationGroup $modification) {
                        return collect($modification->getModifications())
                            ->map(function (PosterDishModification $modification) {
                                $transformer = new PosterDishModificationAsSalesboxOffer($modification);
                                return $transformer->transform();
                            });
                    });
            })
            ->flatten()
            ->toArray();

        if (count($group_modificatons_as_salesbox_offers) > 0) {
            SalesboxStore::createManyOffers($group_modificatons_as_salesbox_offers);
        }
    }

    public function updateMultipleOffers($ids = [])
    {
        /**
         * @var PosterDishModification[] $dish_modifications
         */
        $dish_modifications = collect(PosterStore::findProductsWithModificationGroups($ids))
            ->map(function (PosterProduct $posterProduct) {
                return collect($posterProduct->getDishModificationGroups())
                    ->filter(function (PosterDishModificationGroup $group) {
                        // skip 'multiple' type modifications
                        // because I don't know how to store them in salesbox
                        // I doubt it is even possible
                        return $group->isSingleType();
                    })
                    ->map(function (PosterDishModificationGroup $group) {
                        return $group->getModifications();
                    });
            })
            ->flatten()
            ->toArray();

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

                if(!$poster_product->hasModification($offer->getModifierId())) {
                    $delete_salesbox_offers[] = $offer;
                }

            }
        }

        foreach ($dish_modifications as $modification) {
            $offer = SalesboxStore::findOfferByExternalId(
                $modification->getGroup()->getProduct()->getProductId(),
                $modification->getDishModificationId()
            );
            if (!$offer) {
                $transformer = new PosterDishModificationAsSalesboxOffer($modification);
                $create_salesbox_offers[] = $transformer->transform();
            } else {
                $transformer = new PosterDishModificationAsSalesboxOffer($modification);
                $update_salesbox_offers[] = $transformer->updateFrom($offer);
            }
        }

        if (count($create_salesbox_offers)) {
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
