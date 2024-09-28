<?php

namespace App\Salesbox\Listeners;

use App\Poster\Facades\PosterStore;
use App\Poster\ServiceMode;
use App\Salesbox\Events\SalesboxOrderCreated;
use App\Salesbox\Facades\SalesboxStore;
use App\Salesbox\Models\SalesboxOrderOffer;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use poster\src\PosterApi;


class SalesboxOrderSendToPoster
{
    public $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(SalesboxOrderCreated $event)
    {
        $content = $this->request->getContent();
        SalesboxStore::authenticate();
        PosterStore::init();
        $rawOrder = json_decode($content, true);
        if(cache()->get("salesbox_order_{$rawOrder['id']}_is_sent")) {
            // Log::info('order was attempted to be sent for the second time. It was prevented');
            return true;
        }

        $order = SalesboxStore::getOrderById($rawOrder['id']);
        $user = $order->getUser();

        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();

        $incomingOrder = [
            'spot_id' => 1,
            'phone' => $order->getPhone(),
            // PosterPost will throw validation error when first name less than 2 characters
            'first_name' => ($firstName && strlen($firstName) > 1) ? $firstName: null,
            // the same with the last name
            'last_name' => ($lastName && strlen($lastName)) > 1 ? $lastName: null,
            'email' => $user->getEmail() ?: null,
            'service_mode' => ServiceMode::ON_SITE,
            // Don't remove SalesboxOrderID from the comment.
            // Custom extension of the PosterPos platform relies on this ID to determine
            // how many bonuses the user of the salesbox app applied to the order
            'comment' => sprintf(
                '%s: %s. %s; SalesboxOrderID: %s',
                trans('salesbox::trans.way_of_communication'),
                trans('salesbox::trans.type.' . $order->getWayOfCommunicationId()),
                $order->getComment(),
                $order->getId()
            )
        ];

        if (!$order->isExecuteNow()) {
            $executeDatetime = DateTime::createFromFormat('Y-m-d\TH:i:s+', $order->getExecuteDate());
            // delivery time must be in the future
            if ($executeDatetime->getTimestamp() > time()) {
                // order's execute time is in UTC timezone
                // but poster may be in different timezone
                $posterTimeZones = PosterApi::settings()->getTimeZones();
                $prevTimeZone = date_default_timezone_get();
                date_default_timezone_set($posterTimeZones->response->value); // Europe/Kiev
                $formattedDeliveryTime = date("Y-m-d H:i:s", $executeDatetime->getTimestamp());
                date_default_timezone_set($prevTimeZone);

                $incomingOrder['delivery_time'] = $formattedDeliveryTime;
            }
        }

        if ($order->isCourierDeliveryType()) {
            $incomingOrder['service_mode'] = ServiceMode::COURIER;
        }

        if ($order->isPickupDeliveryType()) {
            $incomingOrder['service_mode'] = ServiceMode::TAKEAWAY;
        }

        if ($order->getAddressName()) {
            $incomingOrder['client_address'] = [
                'address1' => $order->getAddressName(),
                'address2' => null,
                'comment' => $order->getDeliveryComment(),
                // sometimes addressLatitude and addressLongitude value may be "null" as string
                'lat' => $order->getAddressLatitude() !== "null" ? $order->getAddressLatitude(): null,
                'lng' => $order->getAddressLongitude() !== "null" ? $order->getAddressLongitude(): null,
            ];
        }

        $incomingOrder['products'] = collect($order->getOffers())
            ->map(function (SalesboxOrderOffer $offer) {
                return [
                    'product_id' => $offer->getExternalId(),
                    'count' => $offer->getCount(),
                    'modificator_id' => $offer->getModifierId(),
                ];
            });

        $res = PosterApi::incomingOrders()->createIncomingOrder($incomingOrder);
        if (!isset($res->response)) {
            throw new \RuntimeException(sprintf("Couldn't send salesbox order#%d to poster: ", $order->getOrderNumber()) . json_encode($res));
        }
        cache()->put("salesbox_order_{$order->getId()}_is_sent", true, 60 * 2); // cache for 2 minutes

        return true;
    }

}
