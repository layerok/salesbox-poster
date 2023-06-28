<?php

namespace App\Http\Controllers\PosterApp;

use App\Poster\Facades\PosterStore;
use App\Poster\Transformers\PosterCategoryAsSalesboxCategory;
use App\Salesbox\Facades\SalesboxStore;
use App\Salesbox\Models\SalesboxCategory;
use Illuminate\Support\Facades\Request;
use poster\src\PosterApi;

class SyncCategoriesController
{

    public function __invoke($code = null)
    {
        $input = Request::all();
        SalesboxStore::authenticate();

        $accessToken = cache()->get($code);
        $config = config('poster');
        PosterApi::init([
            'application_id' => $config['application_id'],
            'application_secret' => $config['application_secret'],
            'account_name' => $config['account_name'],
            'access_token' => $accessToken,
        ]);

        $poster_categories = PosterStore::loadCategories();
        $salesbox_categories = SalesboxStore::loadCategories();
        $delete_categories = [];
        $create_categories = [];
        $update_categories = [];

        foreach ($poster_categories as $poster_category) {
            if($poster_category->isTopScreen()) {
                continue;
            }
            $salesbox_category = SalesboxStore::findCategoryByExternalId($poster_category->getCategoryId());
            if ($salesbox_category) {
                $transformer = new PosterCategoryAsSalesboxCategory($poster_category);
                $update_categories[] = $transformer->updateFrom($salesbox_category);
            } else {
                $transformer = new PosterCategoryAsSalesboxCategory($poster_category);
                $create_categories[] = $transformer->transform();
            }
        }

        foreach ($salesbox_categories as $salesbox_category) {
            if ($salesbox_category->getExternalId()) {
                if (!PosterStore::categoryExists($salesbox_category->getExternalId())) {
                    $delete_categories[] = $salesbox_category;
                }
            } else {
                // todo: should I delete categories not connected to poster?
                $delete_categories[] = $salesbox_category;
            }

        }
        if (count($create_categories) > 0 && isset($input['create'])) {
            SalesboxStore::createManyCategories($create_categories);
        }
        if (count($update_categories) > 0 && isset($input['update'])) {

            array_map(function (SalesboxCategory $salesbox_category) {
                // don't update names and photos
                $salesbox_category->resetAttributeToOriginalOne('previewURL');
                $salesbox_category->resetAttributeToOriginalOne('names');
                $salesbox_category->resetAttributeToOriginalOne('available');
            }, $update_categories);
            SalesboxStore::updateManyCategories($update_categories);
        }
        if (count($delete_categories) > 0 && isset($input['delete'])) {
            SalesboxStore::deleteManyCategories($delete_categories);
        }

        return back();
    }

}
