<?php

namespace App\Http\Controllers;

use App\Poster\Facades\PosterStore;
use App\Salesbox\Facades\SalesboxStore;
use GuzzleHttp\Client;
use poster\src\PosterApi;

class PosterAppController
{
    public function authorize($code) {
        $auth = [
            'application_id' => config('poster.application_id'),
            'application_secret' => config('poster.application_secret'),
            'code' => $code,
        ];
        $auth['verify'] = md5(implode(':', $auth));

        $client = new Client([
            'http_errors' => false
        ]);

        $response = $client->post('https://joinposter.com/api/v2/auth/manage', [
            'form_params' => $auth
        ]);

        return json_decode($response->getBody(), true);
    }
    public function __invoke($code = null)
    {

        $accessToken = cache()->get($code);
        if(!$accessToken) {
            $res = $this->authorize($code);

            if (isset($res['error'])) {
                return view('poster-app-error', $res);
            }

            $accessToken = $res['access_token'];
            cache()->put($code, $accessToken);
        }

        SalesboxStore::authenticate();
        $config = config('poster');
        PosterApi::init([
            'application_id' => $config['application_id'],
            'application_secret' => $config['application_secret'],
            'account_name' => $config['account_name'],
            'access_token' => $accessToken,
        ]);

        $salesboxCategories = SalesboxStore::loadCategories();
        $posterCategories = PosterStore::loadCategories();

        $categories = [];
        $products = [];

        foreach ($salesboxCategories as $salesboxCategory) {
            if ($salesboxCategory->getExternalId()) {
                $posterCategory = PosterStore::findCategory($salesboxCategory->getExternalId());
                if (!$posterCategory) {
                    $categories[] = [
                        'name' => $salesboxCategory->getAttributes('name'),
                        'poster' => [
                            'created' => false
                        ],
                        'salesbox' => [
                            'created' => true,
                            'id' =>  $salesboxCategory->getId(),
                        ],
                    ];
                } else {
                    if($posterCategory->isVisible() != $salesboxCategory->getAvailable()) {
                        $categories[] = [
                            'name' => $posterCategory->getCategoryName(),
                            'poster' => [
                                'created' => true,
                                'id' => $posterCategory->getCategoryId()
                            ],
                            'salesbox' => [
                                'created' => true,
                                'id' => $salesboxCategory->getId(),
                                'stale' => true
                            ],
                        ];
                    }
                }
            } else {
                $categories[] = [
                    'name' => $salesboxCategory->getAttributes('name'),
                    'poster' => [
                        'created' => false
                    ],
                    'salesbox' => [
                        'created' => true,
                        'id' => $salesboxCategory->getId()
                    ],

                ];
            }
        }

        foreach ($posterCategories as $posterCategory) {
            if($posterCategory->isTopScreen()) {
                continue;
            }
            if (!SalesboxStore::categoryExistsWithExternalId($posterCategory->getCategoryId())) {
                $categories[] = [
                    'name' => $posterCategory->getCategoryName(),
                    'poster' => [
                        'created' => true,
                        'id' =>  $posterCategory->getCategoryId(),
                    ],
                    'salesbox' => [
                        'created' => false
                    ],
                ];
            }
        }

        $posterProducts = PosterStore::loadProducts();
        $salesboxOffers = SalesboxStore::loadOffers();


        foreach ($posterProducts as $posterProduct) {
            if ($posterProduct->isDishType()) {
                if ($posterProduct->hasDishModificationGroups()) {

                } else {
                    if (!SalesboxStore::offerExistsWithExternalId($posterProduct->getProductId())) {
                        $products[] = [
                            'name' => $posterProduct->getProductName(),
                            'poster' => [
                                'created' => true,
                                'id' => $posterProduct->getProductId(),
                            ],
                            'salesbox' => [
                                'created' => false
                            ],

                        ];

                    }
                }
            } else {
                if ($posterProduct->hasProductModifications()) {

                } else {
                    if (!SalesboxStore::offerExistsWithExternalId($posterProduct->getProductId())) {
                        $products[] = [
                            'name' => $posterProduct->getProductName(),
                            'poster' => [
                                'created' => true,
                                'id' => $posterProduct->getProductId()
                            ],
                            'salesbox' => [
                                'created' => false
                            ],

                        ];
                    }
                }
            }

        }

        foreach ($salesboxOffers as $salesboxOffer) {
            if ($salesboxOffer->getExternalId()) {
                if (!PosterStore::productExists($salesboxOffer->getExternalId())) {
                    $products[] = [
                        'name' => $salesboxOffer->getAttributes('name'),
                        'poster' => [
                            'created' => false
                        ],
                        'salesbox' => [
                            'created' => true,
                            'id' => $salesboxOffer->getId(),

                        ],

                    ];
                }
            } else {
                $products[] = [
                    'name' => $salesboxOffer->getAttributes('name'),
                    'poster' => [
                        'created' => false
                    ],
                    'salesbox' => [
                        'created' => true,

                        'id' => $salesboxOffer->getId()
                    ],

                ];

            }
        }


        return view('poster-app', [
            'code' => $code,
            'categories' => $categories,
            'products' => $products
        ]);
    }
}
