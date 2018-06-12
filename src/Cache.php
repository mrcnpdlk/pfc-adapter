<?php /** @noinspection MoreThanThreeArgumentsInspection */

namespace mrcnpdlk\Lib\PfcAdapter;


use Phpfastcache\CacheManager;
use Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Cache
 *
 * @package mrcnpdlk\Lib\PfcAdapter
 */
class Cache
{
    /**
     * @var \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
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
     * Generate hash with project hash
     *
     * @var boolean
     */
    private $uniqueHash;
    /**
     * @var \Psr\Log\NullLogger | LoggerInterface
     */
    private $logger;

    /**
     * Cache constructor.
     *
     * @param \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface|null $oCache
     * @param bool                                                        $uniqueHash
     */
    public function __construct(ExtendedCacheItemPoolInterface $oCache = null, bool $uniqueHash = true)
    {
        $this->oCache      = $oCache ?? CacheManager::Redis();
        $this->projectHash = md5(__DIR__);
        $this->defaultTtl  = $this->oCache->getConfig()->getDefaultTtl();
        $this->uniqueHash  = $uniqueHash;
        $this->logger      = new NullLogger();
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
     * @param array $tHashKeys
     *
     * @return string
     */
    public function genHash(array $tHashKeys): string
    {
        if ($this->uniqueHash) {
            return md5(json_encode(array_merge($tHashKeys, [$this->projectHash])));
        }

        return md5(json_encode($tHashKeys));
    }/** @noinspection MoreThanThreeArgumentsInspection */

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->userData;
    }

    /**
     * @return \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
     */
    public function getHandler(): ExtendedCacheItemPoolInterface
    {
        return $this->oCache;
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
    ): self {
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
                    $this->logger->debug(sprintf('CACHE [%s]: old or NULL, reset [ttl=%s]', $hashKey, $iCache));
                    $this->userData = $inputDataFunction();
                    $oCachedItem
                        ->set($this->userData)
                        ->setTags($tRedisTags)
                        ->expiresAfter($iCache)
                    ;
                    $this->oCache->save($oCachedItem);
                } else {
                    $this->logger->debug(sprintf('CACHE [%s]: getting from cache', $hashKey));
                    $this->userData = $oCachedItem->get();
                }
            } else {
                $this->logger->debug(sprintf('CACHE [%s]: cache omitted [ttl=0]', $hashKey));
                $this->userData = $inputDataFunction();
            }

            return $this;
        } catch (\Exception $e) {
            $this->userData = $inputDataFunction();

            return $this;
        }
    }

    /**
     * @param \Psr\Log\LoggerInterface $oLogger
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $oLogger): self
    {
        $this->logger = $oLogger;

        return $this;
    }
}
