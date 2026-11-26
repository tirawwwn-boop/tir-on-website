<?php
defined('ABSPATH') || exit;

class Tiron_RO_Delivery extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        $this->id = 'tiron_ro';
        $this->instance_id = absint($instance_id);
        $this->method_title = 'Livrare TIR-ON · România';
        $this->method_description = 'Tarif fix pe expediere. Completează tariful pentru a oferi metoda.';
        $this->supports = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];
        $this->instance_form_fields = [
            'title' => ['title' => 'Denumire la checkout', 'type' => 'text', 'default' => 'Livrare'],
            'cost' => ['title' => 'Tarif pe expediere în moneda magazinului', 'type' => 'price', 'default' => '', 'description' => 'Gol = indisponibil. Zero înseamnă transport gratuit.'],
            'tax_status' => ['title' => 'Taxare', 'type' => 'select', 'default' => 'taxable', 'options' => ['taxable' => 'Taxabil', 'none' => 'Fără taxă']],
            'estimate' => ['title' => 'Estimare după expediere', 'type' => 'text', 'default' => ''],
        ];
        $this->title = $this->get_option('title', 'Livrare');
        $this->tax_status = $this->get_option('tax_status', 'taxable');
    }

    public function calculate_shipping($package = []) {
        $cost = $this->get_option('cost', '');
        if (($package['destination']['country'] ?? '') !== 'RO' || !is_numeric($cost) || (float)$cost < 0) return;
        $estimate = trim($this->get_option('estimate', ''));
        $this->add_rate(['id' => $this->get_rate_id(), 'label' => $this->title . ($estimate !== '' ? ' · ' . $estimate : ''),
            'cost' => $cost, 'package' => $package]);
    }
}
