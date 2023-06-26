<?php

namespace App\Poster\Listeners;

use App\Poster\Events\Category\PosterCategoryRemoved;
use App\Salesbox\Facades\SalesboxStore;
use Illuminate\Http\Request;

class PosterCategoryRemoveInSalesbox
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(PosterCategoryRemoved $event)
    {
        SalesboxStore::authenticate();
        SalesboxStore::loadCategories();

        $salesbox_category = SalesboxStore::findCategoryByExternalId($event->getObjectId());

        if (!$salesbox_category) {
            return true;
        }

        // it also deletes child categories, if they exist
        SalesboxStore::deleteCategory($salesbox_category);

        return true;
    }


}
