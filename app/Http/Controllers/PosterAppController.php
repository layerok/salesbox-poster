<?php

namespace App\Http\Controllers;

use App\Poster\Facades\PosterStore;
use App\Salesbox\Facades\SalesboxStore;
use Illuminate\Http\Request;
use poster\src\PosterApi;

class PosterAppController
{

    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function __invoke($code = null)
    {
        SalesboxStore::authenticate();
        $config = config('poster');
        PosterApi::init([
            'application_id' => $config['application_id'],
            'application_secret' => $config['application_secret'],
            'account_name' => $config['account_name'],
            'access_token' => $config['access_token'],
        ]);

        $categories = $this->getCategoriesTable();
        $products = $this->getProductsTable();

        $categoriesSynced = true;
        $productsSynced = true;

        foreach($categories as $category) {
            if(!$category['poster']['created'] || !$category['salesbox']['created']) {
                $categoriesSynced = false;
            }
        }

        foreach($products as $product) {
            if(!$product['poster']['created'] || !$product['salesbox']['created']) {
                $productsSynced = false;
            }
        }


        return view('poster-app', [
            'code' => $code,
            'categories' => $categories,
            'products' => $products,
            'categoriesSynced' => $categoriesSynced,
            'productsSynced' => $productsSynced
        ]);
    }

    public function getProductsTable()
    {
        $posterProducts = PosterStore::loadProducts();
        $salesboxOffers = SalesboxStore::loadOffers();
        $products = [];

        foreach ($posterProducts as $posterProduct) {
            if ($posterProduct->hasDishModificationGroups()) {
                $modificationGroups = $posterProduct->getDishModificationGroups();

                foreach($modificationGroups as $group) {
                    if($group->isMultipleType()) {
                        continue;
                    }

                    $modifications = $group->getModifications();

                    if(count($modifications) > 0) {
                        continue;
                    }

                    // treat like a normal product without any modifications
                    $products[] = [
                        'name' => $posterProduct->getProductName(),
                        'poster' => [
                            'id' => $posterProduct->getProductId(),
                            'created' => true,
                        ],
                        'salesbox' => [
                            'created' => !!SalesboxStore::findOfferByExternalId($posterProduct->getProductId())
                        ]
                    ];
                }

                // skip dish with 'multiple' modification groups
                continue;
            }

            if ($posterProduct->hasProductModifications()) {
                foreach ($posterProduct->getProductModifications() as $modification) {
                    $products[] = [
                        'name' => $posterProduct->getProductName() . ', ' . $modification->getModificatorName(),
                        'poster' => [
                            'id' => $posterProduct->getProductId(),
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
                    'id' => $posterProduct->getProductId(),
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
                            'id' => $posterProduct->getProductId(),
                            'created' => !!$modification,
                        ],
                        'salesbox' => [
                            'created' => true,
                        ]
                    ];
                } else {
                    $posterProduct = PosterStore::findProduct($salesboxOffer->getExternalId());
                    $products[] = [
                        'name' => $posterProduct ? $posterProduct->getProductName() : $salesboxOffer->getAttributes('name'),
                        'poster' => [
                            'id' => $posterProduct->getProductId(),
                            'created' => !!$posterProduct,
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
                    'id' => null,
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
                        'id' => $salesboxCategory->getExternalId(),
                        'created' => PosterStore::categoryExists($salesboxCategory->getExternalId())
                    ],
                    'salesbox' => [
                        'id' => $salesboxCategory->getId(),
                        'created' => true,
                    ],
                ];

            } else {
                $categories[] = [
                    'name' => $salesboxCategory->getAttributes('name'),
                    'poster' => [
                        'id' => null,
                        'created' => false
                    ],
                    'salesbox' => [
                        'id' =>  $salesboxCategory->getId(),
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
                        'id' => $posterCategory->getCategoryId(),
                        'created' => true,
                    ],
                    'salesbox' => [
                        'id' => null,
                        'created' => false
                    ],
                ];
            }
        }
        return $categories;
    }
}
