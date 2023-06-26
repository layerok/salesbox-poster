<?php

namespace App\Poster\Listeners;

use App\Poster\Events\Category\PosterCategoryActionPerformed;
use App\Poster\Facades\PosterStore;
use App\Poster\Models\PosterCategory;
use App\Poster\Transformers\PosterCategoryAsSalesboxCategory;
use App\Salesbox\Facades\SalesboxStore;
use App\Salesbox\Models\SalesboxCategory;
use Illuminate\Http\Request;

class PosterCategorySyncWithSalesbox
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(PosterCategoryActionPerformed $event)
    {
        if ($event->isAdded() || $event->isRestored() || $event->isChanged()) {
            SalesboxStore::authenticate();
            $categories = PosterStore::loadCategories();
            SalesboxStore::loadCategories();

            $poster_category = PosterStore::findCategory($event->getObjectId());

            if (!PosterStore::categoryExists($event->getObjectId())) {
                throw new \RuntimeException(sprintf('category#%s not found in poster', $event->getObjectId()));
            }

            $update_ids = [];
            $create_ids = [];

            if (SalesboxStore::categoryExistsWithExternalId($event->getObjectId())) {
                $update_ids[] = $event->getObjectId();
            } else {
                $create_ids[] = $event->getObjectId();
            }

            if ($poster_category->hasParentCategory()) {
                $poster_parent_categories = PosterStore::getCategoryParents($poster_category);

                foreach ($poster_parent_categories as $parent_category) {
                    if (!SalesboxStore::categoryExistsWithExternalId($parent_category->getCategoryId())) {
                        $create_ids[] = $parent_category->getCategoryId();
                    }
                }
            }

            // make updates
            if (count($create_ids) > 0) {
                $poster_categories_as_salesbox_ones = array_map(function(PosterCategory $posterCategory) {
                    $transformer = new PosterCategoryAsSalesboxCategory($posterCategory);
                    return $transformer->transform();
                }, PosterStore::findCategory($create_ids));

                SalesboxStore::createManyCategories($poster_categories_as_salesbox_ones);
            }

            if (count($update_ids) > 0) {
                $poster_categories_as_salesbox_ones = array_map(function(PosterCategory $posterCategory) {
                    $salesbox_category = SalesboxStore::findCategoryByExternalId($posterCategory->getCategoryId());
                    $transformer = new PosterCategoryAsSalesboxCategory($posterCategory);
                    return $transformer->updateFrom($salesbox_category);
                }, PosterStore::findCategory($update_ids));

                array_map(function (SalesboxCategory $salesbox_category) {
                    // don't override photo if it is already present
                    if ($salesbox_category->getOriginalAttributes('previewURL')) {
                        $salesbox_category->resetAttributeToOriginalOne('previewURL');
                    }

                    if ($salesbox_category->getOriginalAttributes('originalURL')) {
                        $salesbox_category->resetAttributeToOriginalOne('originalURL');
                    }

                    // the same applies to 'names'
                    if (count($salesbox_category->getOriginalAttributes('names')) > 0) {
                        $salesbox_category->resetAttributeToOriginalOne('names');
                    }
                }, $poster_categories_as_salesbox_ones);

                SalesboxStore::updateManyCategories($poster_categories_as_salesbox_ones);
            }
        }
    }


}
