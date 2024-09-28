<?php

namespace App\Providers;

use App\Poster\Events\Category\PosterCategoryActionPerformed;
use App\Poster\Events\Category\PosterCategoryRemoved;
use App\Poster\Events\Dish\PosterDishActionPerformed;
use App\Poster\Events\Dish\PosterDishRemoved;
use App\Poster\Events\Product\PosterProductActionPerformed;
use App\Poster\Events\Product\PosterProductRemoved;
use App\Poster\Listeners\PosterCategoryRemoveInSalesbox;
use App\Poster\Listeners\PosterCategorySyncWithSalesbox;
use App\Poster\Listeners\PosterDishRemoveInSalesbox;
use App\Poster\Listeners\PosterDishSyncWithSalesbox;
use App\Poster\Listeners\PosterProductRemoveInSalesbox;
use App\Poster\Listeners\PosterProductSyncWithSalesbox;
use App\Salesbox\Events\SalesboxOrderCreated;
use App\Salesbox\Events\SalesboxWebhookReceived;
use App\Salesbox\Listeners\SalesboxOrderSendToPoster;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        SalesboxWebhookReceived::class => [],

        SalesboxOrderCreated::class => [
            SalesboxOrderSendToPoster::class
        ],

        PosterDishRemoved::class => [
            PosterDishRemoveInSalesbox::class,
        ],
        PosterDishActionPerformed::class => [
            PosterDishSyncWithSalesbox::class,
        ],

        PosterProductRemoved::class => [
            PosterProductRemoveInSalesbox::class,
        ],
        PosterProductActionPerformed::class=> [
            PosterProductSyncWithSalesbox::class,
        ],

//        PosterCategoryRemoved::class => [
//            PosterCategoryRemoveInSalesbox::class,
//        ],
//        PosterCategoryActionPerformed::class=> [
//            PosterCategorySyncWithSalesbox::class,
//        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
