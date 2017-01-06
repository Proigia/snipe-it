<?php
/**
 * Created by PhpStorm.
 * User: parallelgrapefruit
 * Date: 12/24/16
 * Time: 1:03 PM
 */

namespace App\Importer;

use App\Helpers\Helper;
use App\Models\Asset;
use App\Models\Component;

class ComponentImporter extends ItemImporter
{
    protected $components;
    function __construct($filename, $logCallback, $progressCallback, $errorCallback, $testRun = false, $user_id = -1, $updating = false, $usernameFormat = null)
    {
        parent::__construct($filename, $logCallback, $progressCallback, $errorCallback, $testRun, $user_id, $updating, $usernameFormat);
        $this->components = Component::all();
    }

    protected function handle($row)
    {
        parent::handle($row); // TODO: Change the autogenerated stub
        $this->createComponentIfNotExists();
    }

    /**
     * Create a component if a duplicate does not exist
     *
     * @author Daniel Melzter
     * @since 3.0
     */
    public function createComponentIfNotExists()
    {
        $component = null;
        $editingComponent = false;
        $this->log("Creating Component");
        $component = $this->components->search(function ($key) {
            return strcasecmp($key->name, $this->item['item_name']) == 0;
        });
        if ($component !== false) {
            $editingComponent = true;
            if (!$this->updating) {
                $this->log('A matching Component ' . $this->item["item_name"] . ' already exists.  ');
                return;
            }
        } else {
            $this->log("No matching component, creating one");
            $component = new Component();
        }

        if (!$editingComponent) {
            $component->name = $this->item["item_name"];
        }
        if (!empty($this->item["purchase_date"])) {
            $component->purchase_date = $this->item["purchase_date"];
        } else {
            $component->purchase_date = null;
        }

        if (!empty($this->item["purchase_cost"])) {
            $component->purchase_cost = Helper::ParseFloat($this->item["purchase_cost"]);
        }
        if (isset($this->item["location"])) {
            $component->location_id = $this->item["location"]->id;
        }
        $component->user_id = $this->user_id;
        if (isset($this->item["company"])) {
            $component->company_id = $this->item["company"]->id;
        }
        if (!empty($this->item["order_number"])) {
            $component->order_number = $this->item["order_number"];
        }
        if (isset($this->item["category"])) {
            $component->category_id = $this->item["category"]->id;
        }

        // TODO:Implement
        //$component->notes= e($this->item_notes);
        if (!empty($this->item["requestable"])) {
            $component->requestable = filter_var($this->item["requestable"], FILTER_VALIDATE_BOOLEAN);
        }

        $component->qty = 1;
        if ( (!empty($this->item["quantity"])) && ($this->item["quantity"] > -1)) {
                $component->qty = $this->item["quantity"];
        }

        if ($this->testRun) {
            $this->log('TEST RUN - Component ' . $this->item['item_name'] . ' not created');
            return;
        }
        if ($component->save()) {
            $component->logCreate('Imported using CSV Importer');
            $this->log("Component " . $this->item["item_name"] . ' was created');

            // If we have an asset tag, checkout to that asset.
            if(isset($this->item['asset_tag']) && ($asset = Asset::where('asset_tag', $this->item['asset_tag'])->first()) ) {
                $component->assets()->attach($component->id, [
                    'component_id' => $component->id,
                    'user_id' => $this->user_id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'assigned_qty' => 1, // Only assign the first one to the asset
                    'asset_id' => $asset->id
                ]);
            }
            return;
        }
        $this->jsonError($component, 'Component', $component->getErrors());
    }
}