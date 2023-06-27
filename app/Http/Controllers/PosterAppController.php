<?php

namespace App\Http\Controllers;
use App\Poster\Facades\PosterStore;
use App\Salesbox\Facades\SalesboxStore;
use GuzzleHttp\Client;

class PosterAppController {
    public function __invoke ($code = null) {
        $auth = [
            'application_id'         => config('poster.application_id'),
            'application_secret'     => config('poster.application_secret'),
            'code'                  => $code,
        ];
        $auth['verify'] = md5(implode(':', $auth));

        $client = new Client([
            'http_errors' => false
        ]);

        $response = $client->post('https://joinposter.com/api/v2/auth/manage', [
            'form_params' => $auth
        ]);

        $data = json_decode($response->getBody(), true);

        if(isset($data['error'])) {
            return view('poster-app-error', $data);
        }

        SalesboxStore::authenticate();
        PosterStore::init();

        $salesboxCategories = SalesboxStore::loadCategories();
        $posterCategories = PosterStore::loadCategories();

        $categories = [];
        $products = [];

        foreach($salesboxCategories as $salesboxCategory) {
            if($salesboxCategory->getExternalId()) {
                if(!PosterStore::categoryExists($salesboxCategory->getExternalId())) {
                    $categories[] = [
                        'name' => $salesboxCategory->getNames()[0],
                        'poster' => false,
                        'salesbox' => true
                    ];
                }
            } else {
                $categories[] = [
                    'name' => $salesboxCategory->getNames()[0],
                    'poster' => false,
                    'salesbox' => true
                ];
            }
        }

        foreach($posterCategories as $posterCategory) {
            if(!SalesboxStore::categoryExistsWithExternalId($posterCategory->getCategoryId())) {
                $categories[] = [
                    'name' => $posterCategory->getCategoryName(),
                    'poster' => true,
                    'salesbox' => false
                ];
            }
        }

        $posterProducts = PosterStore::loadProducts();
        $salesboxOffers = SalesboxStore::loadOffers();


        foreach($posterProducts as $posterProduct) {
            if($posterProduct->isDishType()) {
                if($posterProduct->hasDishModificationGroups()) {

                } else {
                    if(!SalesboxStore::offerExistsWithExternalId($posterProduct->getProductId())) {
                        $products[] = [
                            'name' => $posterProduct->getProductName(),
                            'poster' => true,
                            'salesbox' => false
                        ];

                    }
                }
            } else {
                if($posterProduct->hasProductModifications()) {

                } else {
                    if(!SalesboxStore::offerExistsWithExternalId($posterProduct->getProductId())) {
                        $products[] = [
                            'name' => $posterProduct->getProductName(),
                            'poster' => true,
                            'salesbox' => false
                        ];
                    }
                }
            }

        }

        foreach ($salesboxOffers as $salesboxOffer) {
            if($salesboxOffer->getExternalId()) {
                if(!PosterStore::productExists($salesboxOffer->getExternalId())) {
                    $products[] = [
                        'name' => $salesboxOffer->getNames()[0],
                        'poster' => false,
                        'salesbox' => true
                    ];
                }
            } else {
                if(!PosterStore::productExists($salesboxOffer->getExternalId())) {
                    $products[] = [
                        'name' => $salesboxOffer->getAttributes('name'),
                        'poster' => false,
                        'salesbox' => true
                    ];
                }
            }
        }


        return view('poster-app', [
            'code' => $code,
            'categories' => $categories,
            'products' => $products
        ]);
    }
}
