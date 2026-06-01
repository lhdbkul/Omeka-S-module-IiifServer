<?php declare(strict_types=1);

namespace IiifServer\View\Helper;

trait TraitDefaultLogoUrl
{
    protected function defaultLogoUrl(): ?string
    {
        $view = $this->getView();
        $setting = $view->plugin('setting');

        $assetId = $setting('iiifserver_manifest_logo_default_asset');
        if ($assetId) {
            try {
                $asset = $view->api()->read('assets', ['id' => $assetId])->getContent();
                return $asset->assetUrl();
            } catch (\Throwable $e) {
            }
        }

        return $setting('iiifserver_manifest_logo_default') ?: null;
    }
}
