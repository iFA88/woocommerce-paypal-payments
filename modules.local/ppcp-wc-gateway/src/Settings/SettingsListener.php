<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\Webhooks\WebhookRegistrar;
use Psr\SimpleCache\CacheInterface;

class SettingsListener
{

    public const NONCE = 'ppcp-settings';
    private $settings;
    private $settingFields;
    private $webhookRegistrar;
    private $cache;
    private $state;
    public function __construct(
        Settings $settings,
        array $settingFields,
        WebhookRegistrar $webhookRegistrar,
        CacheInterface $cache,
        State $state
    ) {

        $this->settings = $settings;
        $this->settingFields = $settingFields;
        $this->webhookRegistrar = $webhookRegistrar;
        $this->cache = $cache;
        $this->state = $state;
    }

    public function listen()
    {

        if (! $this->isValidUpdateRequest()) {
            return;
        }

        /**
         * Nonce verification is done in self::isValidUpdateRequest
         */
        //phpcs:disable WordPress.Security.NonceVerification.Missing
        if (isset($_POST['save']) && sanitize_text_field(wp_unslash($_POST['save'])) === 'reset') {
            $this->settings->reset();
            $this->settings->persist();
            $this->webhookRegistrar->unregister();
            if ($this->cache->has(PayPalBearer::CACHE_KEY)) {
                $this->cache->delete(PayPalBearer::CACHE_KEY);
            }
            return;
        }

        //phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        /**
         * Sanitization is done at a later stage.
         */
        $rawData = (isset($_POST['ppcp'])) ? (array) wp_unslash($_POST['ppcp']) : [];
        $settings = $this->retrieveSettingsFromRawData($rawData);
        $settings['enabled'] =  isset($_POST['woocommerce_ppcp-gateway_enabled'])
            && absint($_POST['woocommerce_ppcp-gateway_enabled']) === 1;
        foreach ($settings as $id => $value) {
            $this->settings->set($id, $value);
        }
        $this->settings->persist();
        if ($this->cache->has(PayPalBearer::CACHE_KEY)) {
            $this->cache->delete(PayPalBearer::CACHE_KEY);
        }
    }

    //phpcs:disable Inpsyde.CodeQuality.NestingLevel.MaxExceeded
    //phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
    private function retrieveSettingsFromRawData(array $rawData): array
    {
        $settings = [];
        foreach ($this->settingFields as $key => $config) {
            if (! in_array($this->state->currentState(), $config['screens'], true)) {
                continue;
            }
            switch ($config['type']) {
                case 'checkbox':
                    $settings[$key] = isset($rawData[$key]);
                    break;
                case 'text':
                    $settings[$key] = isset($rawData[$key]) ? sanitize_text_field($rawData[$key]) : '';
                    break;
                case 'password':
                    if (empty($rawData[$key])) {
                        break;
                    }
                    $settings[$key] = sanitize_text_field($rawData[$key]);
                    break;
                case 'ppcp-multiselect':
                    $values = isset($rawData[$key]) ? (array) $rawData[$key] : [];
                    $valuesToSave = [];
                    foreach ($values as $index => $rawValue) {
                        $value = sanitize_text_field($rawValue);
                        if (! in_array($value, array_keys($config['options']), true)) {
                            continue;
                        }
                        $valuesToSave[] = $value;
                    }
                    $settings[$key] = $valuesToSave;
                    break;
                case 'select':
                    $options = array_keys($config['options']);
                    $settings[$key] = isset($rawData[$key]) && in_array(
                        sanitize_text_field($rawData[$key]),
                        $options,
                        true
                    ) ? sanitize_text_field($rawData[$key]) : null;
                    break;
            }
        }
        return $settings;
    }
    //phpcs:enable Inpsyde.CodeQuality.NestingLevel.MaxExceeded
    //phpcs:enable Generic.Metrics.CyclomaticComplexity.TooHigh

    private function isValidUpdateRequest(): bool
    {

        if (
            ! isset($_REQUEST['section'])
            || sanitize_text_field(wp_unslash($_REQUEST['section'])) !== 'ppcp-gateway'
        ) {
            return false;
        }

        if (! current_user_can('manage_options')) {
            return false;
        }

        if (
            ! isset($_POST['ppcp-nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['ppcp-nonce'])),
                self::NONCE
            )
        ) {
            return false;
        }
        return true;
    }
}
