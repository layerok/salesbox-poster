<?php

namespace App\Http\Controllers\PosterApp;

use App\Poster\Facades\PosterStore;
use App\Poster\Transformers\PosterCategoryAsSalesboxCategory;
use App\Salesbox\Facades\SalesboxApi;
use App\Salesbox\Facades\SalesboxStore;
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

        if(!SalesboxStore::isCategoriesLoaded()) {
            SalesboxStore::loadCategories();
        }
        if(!PosterStore::isCategoriesLoaded()) {
            PosterStore::loadCategories();
        }

        if(isset($input['create']) && isset($input['create_ids'])) {
            $this->createManyCategoriesByPosterIds($input['create_ids']);
        }
        if(isset($input['update']) && isset($input['update_ids'])) {
            $this->updateManyCategoriesByPosterIds($input['update_ids']);
        }

        if(isset($input['delete']) && isset($input['delete_ids'])) {
            $this->deleteManyCategoriesBySalesboxIds($input['delete_ids']);
        }
        return back();
    }

    public function createManyCategoriesByPosterIds($ids) {
        if(count($ids)) {
            $create_salesbox_categories = [];
            foreach($ids as $id) {
                $salesbox_category = SalesboxStore::findCategoryByExternalId($id);
                $posterCategory = PosterStore::findCategory($id);
                if(!$salesbox_category && $posterCategory) {
                    $transformer = new PosterCategoryAsSalesboxCategory($posterCategory);
                    $create_salesbox_categories[] = $transformer->transform();
                }
            }

            if(count($create_salesbox_categories) > 0) {
                SalesboxStore::createManyCategories($create_salesbox_categories);
            }
        }
    }

    public function updateManyCategoriesByPosterIds($ids) {
        if(count($ids)) {
            $data = [];
            foreach($ids as $id) {
                $posterCategory = PosterStore::findCategory($id);
                $salesboxCategory = SalesboxStore::findCategoryByExternalId($id);
                if($posterCategory && $salesboxCategory) {
                    $data[] = [
                        'id' => $salesboxCategory->getId(),
                        'available' => $posterCategory->isVisible(),
                        'names' => $salesboxCategory->getNames(),
                        'previewURL'=> $salesboxCategory->getPreviewURL(),
                    ];

                }
            }
            if(count($data)) {
                SalesboxApi::updateManyCategories([
                    'categories' => $data
                ]);
            }
        }
    }

    public function deleteManyCategoriesBySalesboxIds($ids) {
        if(count($ids)) {
            SalesboxApi::deleteManyCategories([
                'ids' => $ids,
                'recursively' => true
            ]);
        }
    }
}
