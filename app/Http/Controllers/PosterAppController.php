<?php

namespace App\Http\Controllers;

use App\Poster\Facades\PosterStore;
use App\Salesbox\Facades\SalesboxStore;
use GuzzleHttp\Client;
use poster\src\PosterApi;

class PosterAppController
{
    public function authorize($code)
    {
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
        if (!$accessToken) {
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

        $categories = $this->getCategoriesTable();
        $products = $this->getProductsTable();

        return view('poster-app', [
            'code' => $code,
            'categories' => $categories,
            'products' => $products
        ]);
    }

    public function getProductsTable()
    {
        $posterProducts = PosterStore::loadProducts();
        $salesboxOffers = SalesboxStore::loadOffers();
        $products = [];

        foreach ($posterProducts as $posterProduct) {
            if ($posterProduct->hasDishModificationGroups()) {
                // skip dish with modification groups
                continue;
            }

            if ($posterProduct->hasProductModifications()) {
                foreach ($posterProduct->getProductModifications() as $modification) {
                    $products[] = [
                        'name' => $posterProduct->getProductName() . ', ' . $modification->getModificatorName(),
                        'poster' => [
                            'created' => true,
                        ],
                        'salesbox' => [
                            'created' => !!SalesboxStore::findOfferByExternalId($posterProduct->getProductId(), $modification->getModificatorId())
                        ]
                    ];
                }
                continue;
            }


            $products[] = [
                'name' => $posterProduct->getProductName(),
                'poster' => [
                    'created' => true,
                ],
                'salesbox' => [
                    'created' => !!SalesboxStore::offerExistsWithExternalId($posterProduct->getProductId()),

                ],

            ];


        }

        foreach ($salesboxOffers as $salesboxOffer) {
            if ($salesboxOffer->getExternalId()) {

                if ($salesboxOffer->getModifierId()) {
                    $posterProduct = PosterStore::findProduct($salesboxOffer->getExternalId());
                    $modification = $posterProduct->findProductModification($salesboxOffer->getModifierId());

                    $products[] = [
                        'name' => $salesboxOffer->getAttributes('name') . ' модифікація#' . $salesboxOffer->getModifierId(),
                        'poster' => [
                            'created' => !!$modification,
                        ],
                        'salesbox' => [
                            'created' => true,
                        ]
                    ];
                } else {
                    $products[] = [
                        'name' => $salesboxOffer->getAttributes('name'),
                        'poster' => [
                            'created' => PosterStore::productExists($salesboxOffer->getExternalId()),
                        ],
                        'salesbox' => [
                            'created' => true,
                        ],
                    ];

                }
                continue;
            }

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

        return $products;

    }

    public function getCategoriesTable()
    {
        $salesboxCategories = SalesboxStore::loadCategories();
        $posterCategories = PosterStore::loadCategories();

        $categories = [];


        foreach ($salesboxCategories as $salesboxCategory) {
            if ($salesboxCategory->getExternalId()) {

                $categories[] = [
                    'name' => $salesboxCategory->getAttributes('name'),
                    'poster' => [
                        'created' => PosterStore::categoryExists($salesboxCategory->getExternalId())
                    ],
                    'salesbox' => [
                        'created' => true,
                    ],
                ];

            } else {
                $categories[] = [
                    'name' => $salesboxCategory->getAttributes('name'),
                    'poster' => [
                        'created' => false
                    ],
                    'salesbox' => [
                        'created' => true,
                    ],

                ];
            }
        }

        foreach ($posterCategories as $posterCategory) {
            if ($posterCategory->isTopScreen()) {
                continue;
            }
            if (!SalesboxStore::categoryExistsWithExternalId($posterCategory->getCategoryId())) {
                $categories[] = [
                    'name' => $posterCategory->getCategoryName(),
                    'poster' => [
                        'created' => true,
                    ],
                    'salesbox' => [
                        'created' => false
                    ],
                ];
            }
        }
        return $categories;
    }
}
