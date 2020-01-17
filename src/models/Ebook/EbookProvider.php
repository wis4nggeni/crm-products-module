<?php

namespace Crm\ProductsModule\Ebook;

use Nette\Database\Table\ActiveRow;

class EbookProvider
{
    /** @var EbookProviderInterface[] */
    private $providers = [];

    public function register(EbookProviderInterface $ebookProvider)
    {
        $this->providers[$ebookProvider::identifier()] = $ebookProvider;
    }

    public function getFileTypes(): array
    {
        $fileTypes = [];
        foreach ($this->providers as $provider) {
            $fileTypes[$provider::identifier()] = $provider->getFileTypes();
        }

        return $fileTypes;
    }

    public function getDownloadLinks(ActiveRow $product, ActiveRow $user, ActiveRow $address): array
    {
        $downloadLinks = [];
        foreach ($this->providers as $provider) {
            $links = $provider->getDownloadLinks($product, $user, $address);
            if ($links !== null) {
                $downloadLinks[$provider::identifier()] = $links;
            }
        }

        return $downloadLinks;
    }
}
