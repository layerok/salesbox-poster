<?php

namespace App\Http\Controllers;

use App\Poster\Facades\PosterStore;
use App\Poster\Models\PosterDishModification;
use App\Poster\Models\PosterDishModificationGroup;
use App\Salesbox\Facades\SalesboxApi;
use App\Salesbox\Facades\SalesboxStore;
use App\Salesbox\Models\SalesboxCategory;
use App\Salesbox\Models\SalesboxOfferV4;
use App\Poster\Transformers\PosterCategoryAsSalesboxCategory;
use App\Poster\Transformers\PosterDishModificationAsSalesboxOffer;
use App\Poster\Transformers\PosterProductAsSalesboxOffer;
use App\Poster\Transformers\PosterProductModificationAsSalesboxOffer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class SalesboxController extends BaseController
{

    public function index()
    {
        $records = [];
        $this->setPageTitle('Salesbox', 'Синхронизация меню');
        return view('admin.salesbox.index', compact('records'));
    }





    public function syncProducts()
    {
        try {

        } catch (\Exception $exception) {
            return response('error');
        }
        return response('ok');
    }

}
