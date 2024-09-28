<?php

namespace App\Http\Controllers\PosterApp;

use App\Poster\Facades\PosterStore;
use App\Poster\Models\PosterProduct;
use App\Poster\Transformers\PosterProductAsSalesboxOffer;
use App\Poster\Transformers\PosterProductModificationAsSalesboxOffer;
use App\Salesbox\Facades\SalesboxApi;
use App\Salesbox\Facades\SalesboxStore;
use App\Salesbox\Models\SalesboxOfferV4;
use Illuminate\Support\Facades\Request;
use poster\src\PosterApi;

class SyncProductsController
{

    public function __invoke($code = null)
    {
        $input = Request::all();
        SalesboxStore::authenticate();

        $config = config('poster');
        PosterApi::init([
            'application_id' => $config['application_id'],
            'application_secret' => $config['application_secret'],
            'account_name' => $config['account_name'],
            'access_token' => $config['access_token']
        ]);

        $poster_products = array_filter(PosterStore::loadProducts(), function(PosterProduct $product) {
            return true;
            // todo: checkboxes
            // return in_array($product->getProductId(), [437, 438, 439, 440]);
        });

        $salesbox_offers = array_filter(SalesboxStore::loadOffers(), function(SalesboxOfferV4 $offerV4) {
            return true;
            // todo: checkboxes
            // return in_array($offerV4->getId(), []);
        });

        $update_offers = [];
        $create_offers = [];
        $delete_offers = [];

        foreach ($salesbox_offers as $offer) {
            if (!$offer->getExternalId()) {
                // todo: should I delete offers without external id?
                $delete_offers[] = $offer;
                continue;
            }
            $poster_product = PosterStore::findProduct($offer->getExternalId());
            if (!$poster_product) {
                $delete_offers[] = $offer;
                continue;
            }
            if (!$offer->getModifierId()) {
                continue;
            }
            if (!$poster_product->hasModification($offer->getModifierId())) {
                $delete_offers[] = $offer;
            }
        }

        foreach ($poster_products as $poster_product) {
            if ($poster_product->hasProductModifications()) {
                $modifications = $poster_product->getProductModifications();
                foreach ($modifications as $modification) {
                    $offer = SalesboxStore::findOfferByExternalId($poster_product->getProductId(), $modification->getModificatorId());
                    if ($offer) {
                        $transformer = new PosterProductModificationAsSalesboxOffer($modification);
                        $update_offers[] = $transformer->updateFrom($offer);
                    } else {
                        $transformer = new PosterProductModificationAsSalesboxOffer($modification);
                        $create_offers[] = $transformer->transform();
                    }
                }
            } else if ($poster_product->hasDishModificationGroups()) {
                $groups = $poster_product->getDishModificationGroups();
                foreach($groups as $group) {
                    if($group->isMultipleType()) {
                        continue;
                    }
                    $modifications = $group->getModifications();
                    if(count($modifications) > 0) {
                        continue;
                    }
                    $offer = SalesboxStore::findOfferByExternalId($poster_product->getProductId());
                    if ($offer) {
                        $transformer = new PosterProductAsSalesboxOffer($poster_product);
                        $update_offers[] = $transformer->updateFrom($offer);
                    } else {
                        $transformer = new PosterProductAsSalesboxOffer($poster_product);
                        $create_offers[] = $transformer->transform();
                    }
                }
                // skip dish with modification groups
            } else {
                $offer = SalesboxStore::findOfferByExternalId($poster_product->getProductId());
                if ($offer) {
                    $transformer = new PosterProductAsSalesboxOffer($poster_product);
                    $update_offers[] = $transformer->updateFrom($offer);
                } else {
                    $transformer = new PosterProductAsSalesboxOffer($poster_product);
                    $create_offers[] = $transformer->transform();
                }
            }

        }

        if(count($create_offers) > 0 && isset($input['create'])) {
            SalesboxStore::createManyOffers($create_offers);
        }

        if(count($update_offers) > 0 && isset($input['update'])) {
            $offersAsArray = array_map(function (SalesboxOfferV4 $offer) {
                return [
                    'id' => $offer->getId(),
                    //'categories' => $offer->getCategories(),
                    //'available' => $offer->getAvailable(),
                    'price' => $offer->getPrice(),
                ];
            }, $update_offers);

            SalesboxApi::updateManyOffers([
                'offers' => array_values($offersAsArray)// reindex array, it's important, otherwise salesbox api will fail
            ]);
        }

        if(count($delete_offers) > 0 && isset($input['delete'])) {
            SalesboxStore::deleteManyOffers($delete_offers);
        }


        return back();
    }




}
