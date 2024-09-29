<?php

namespace App\Http\Controllers;

use App\Salesbox\Facades\SalesboxApi;
use App\Salesbox\Facades\SalesboxApiV4;
use App\Salesbox\Models\SalesboxOfferV4;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use poster\src\PosterApi;

use function response;

class PosterWebhookController
{
    public function __invoke(Request $request) {
        try {
            PosterApi::init(config('poster'));

            if(!PosterApi::auth()->verifyWebHook($request->getContent())) {
                return response('Not authorized',200);
            }

            $accessToken = SalesboxApi::getAccessToken()['data']['token'];
            SalesboxApi::setAccessToken($accessToken);
            SalesboxApiV4::setAccessToken($accessToken);

            $postData = $request->post();

            switch ($postData['action']) {
                case "added":
                    $this->createProduct($postData);
                    break;
                case "changed":
                    $this->updateProduct($postData);
                    break;
                case "removed":
                    $this->deleteProduct($postData);
                    break;
            }
        } catch(\Throwable $exception) {
           Log::error($exception->getMessage() . "\n" . $exception->getTraceAsString());
           return response('error');
        } finally {
           return response('ok');
        }
    }

    public function createProduct($postData) {
        $result = (object) PosterApi::menu()->getProduct([
            'product_id' => $postData['object_id']
        ]);

        $posterProduct = $result->response;

        if (!$posterProduct) {
            // we haven't found this product in poster
            return;
        }

        if(isset($posterProduct->modifications)) {
            // we don't support modifications
            return;
        }

        $salesboxOffer = $this->getSalesboxOfferByExternalId($posterProduct->product_id);

        if ($salesboxOffer) {
            // already exists in salesbox,
            // it shouldn't happen
            return;
        }

        $name = $posterProduct->product_name;

        $names = [
            [
                'lang' => 'uk',
                'name' => $name
            ]
        ];

        $photos = [];

        if($posterProduct->photo_origin) {
            $photos[] = [
                'url' => config('poster.url') . (string)$posterProduct->photo_origin,
                'previewURL' => config('poster.url') . (string)$posterProduct->photo,
                'order' => 0,
                'type' => 'image',
                'resourceType' => 'image'
            ];
        }


        $description = collect($posterProduct->ingredients ?? [])->map(function($ingredient) {
            return $ingredient->ingredient_name;
        })->join(', ');

        $descriptions = [
            [
                'lang' => 'uk',
                'description' => $description
            ]
        ];

        $price = (int)substr($posterProduct->price->{'1'}, 0, -2);
        $id = $posterProduct->product_id;

        $offer = [
            'externalId' => $posterProduct->product_id,
            'descriptions' => $descriptions,
            'photos' => $photos,
            'names' => $names,
            'available' => false,
            'price' => $price,
        ];

        SalesboxApi::createManyOffers([
            'offers' => [$offer]
        ]);

        Log::info('New product added [' . $name . ']#' . $id . ' to salesbox. It needs moderation');
    }

    public function updateProduct($postData)
    {
        $result = (object)PosterApi::menu()->getProduct([
            'product_id' => $postData['object_id']
        ]);

        $posterProduct = $result->response;

        if (!$posterProduct) {
            // we haven't found this product in poster
            return;
        }

        if(isset($value->modifications)) {
            // we don't support modifications
            return;
        }

        $salesboxOffer = $this->getSalesboxOfferByExternalId($posterProduct->product_id);

        if(!$salesboxOffer) {
            // we haven't found this product in salesbox
            // let's create it
            $this->createProduct($postData);
            return;
        }

        $price = (int)substr($posterProduct->price->{'1'}, 0, -2);

        SalesboxApi::updateManyOffers([
            'offers' => [
                [
                    'id' => $salesboxOffer->getId(),
                    'price' => $price
                ]
            ]
        ]);
    }

    public function deleteProduct($postData)
    {
        $salesboxOffer = $this->getSalesboxOfferByExternalId((string)$postData['object_id']);

        if(!$salesboxOffer) {
            // nothing to delete
            return;
        }

        SalesboxApi::deleteManyOffers([
            'ids' => [$salesboxOffer->getId()],
        ]);
    }

    public function getSalesboxOfferByExternalId(string $externalId): ?SalesboxOfferV4 {
        return collect(SalesboxApiV4::getOffers([
            'pageSize' => 10000,
        ])['data'])->map(function($item) {
            return new SalesboxOfferV4($item);
        })->first(function(SalesboxOfferV4 $item) use ($externalId) {
            return $externalId === $item->getExternalId();
        });
    }

}
