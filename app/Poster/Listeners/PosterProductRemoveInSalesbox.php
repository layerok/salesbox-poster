<?php

namespace App\Poster\Listeners;

use App\Poster\Events\Product\PosterProductRemoved;
use App\Salesbox\Facades\SalesboxStore;
use Illuminate\Http\Request;

class PosterProductRemoveInSalesbox
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(PosterProductRemoved $event)
    {
        SalesboxStore::authenticate();
        SalesboxStore::loadOffers();

        $offers_to_delete = SalesboxStore::findOfferByExternalId([$event->getObjectId()]);

        if(count($offers_to_delete) > 0) {
            // delete products
            SalesboxStore::deleteManyOffers($offers_to_delete);
        }

        return true;

    }


}
