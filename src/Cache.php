<?php

namespace mrcnpdlk\Lib\PfcAdapter;


use phpFastCache\CacheManager;
use phpFastCache\Core\Pool\ExtendedCacheItemPoolInterface;

/**
 * Class Cache
 *
 * @package mrcnpdlk\Lib\PfcAdapter
 */
class Cache
{
    /**
     * @var \phpFastCache\Core\Pool\ExtendedCacheItemPoolInterface
     */
    private $oCache;
    /**
     * @var mixed
     */
    private $userData;
    /**
     * @var string
     */
    private $projectHash;
    /**
     * @var integer
     */
    private $defaultTtl;

    /**
     * Cache constructor.
     *
     * @param \phpFastCache\Core\Pool\ExtendedCacheItemPoolInterface|null $oCache
     */
    public function __construct(ExtendedCacheItemPoolInterface $oCache = null)
    {
        $this->oCache      = $oCache ?? CacheManager::Redis();
        $this->projectHash = md5(__DIR__);
        $this->defaultTtl  = $this->oCache->getConfigOption('defaultTtl');
    }

    /**
     * @return \phpFastCache\Core\Pool\ExtendedCacheItemPoolInterface
     */
    public function getHandler(): ExtendedCacheItemPoolInterface
    {
        return $this->oCache;
    }

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->userData;
    }

    /**
     * @param callable $inputDataFunction
     * @param null     $tHashKeys
     * @param null     $tRedisTags
     * @param int|null $iCache
     *
     * @return $this
     */
    public function set(
        callable $inputDataFunction,
        $tHashKeys = null,
        $tRedisTags = null,
        int $iCache = null
    ) {
        try {
            $tRedisTags = (array)$tRedisTags;
            $tHashKeys  = (array)$tHashKeys;
            $iCache     = $iCache ?? $this->defaultTtl;

            if (empty($tHashKeys)) {
                throw new Exception('HashKey is required!', 1);
            }

            $hashKey = $this->genHash($tHashKeys);

            if ($iCache > 0) {
                $oCachedItem = $this->oCache->getItem($hashKey);
                //do każdego zapytania dajemy TAG związany z projektem
                $tRedisTags = array_unique(array_merge([$this->projectHash], $tRedisTags));

                if (!$oCachedItem->isHit() || $oCachedItem->get() === null) {
                    $this->userData = $inputDataFunction();
                    $oCachedItem
                        ->set($this->userData)
                        ->setTags($tRedisTags)
                        ->expiresAfter($iCache)
                    ;
                    $this->oCache->save($oCachedItem);
                } else {
                    $this->userData = $oCachedItem->get();
                }
            } else {
                $this->userData = $inputDataFunction();
            }

            return $this;
        } catch (\Exception $e) {
            $this->userData = $inputDataFunction();

            return $this;
        }
    }

    /**
     * @param array $tHashKeys
     *
     * @return string
     */
    public function genHash(array $tHashKeys): string
    {
        return md5(json_encode($tHashKeys));
    }

    /**
     * @param string|array $tTags
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function clearCache($tTags): self
    {
        $tTags = (array)$tTags;
        $this->oCache->deleteItemsByTags($tTags);

        return $this;
    }

    /**
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function clearAllCache(): self
    {
        $this->oCache->deleteItemsByTag($this->projectHash);

        return $this;
    }
}