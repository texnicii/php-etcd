<?php

namespace Aternos\Etcd;

use Aternos\Etcd\Exception\InvalidClientException;
use Etcdserverpb\Compare;
use Etcdserverpb\RequestOp;
use Etcdserverpb\TxnResponse;
use Flexihash\Exception;
use Flexihash\Flexihash;
use Generator;

/**
 * Class ShardedClient
 *
 * @package Aternos\Etcd
 */
class ShardedClient implements ClientInterface
{
    /**
     * @var ClientInterface[]
     */
    protected $clients = [];

    /**
     * @var ClientInterface[]
     */
    protected $keyCache = [];

    /**
     * @var Flexihash
     */
    protected $hash = null;

    /**
     * ShardedClient constructor.
     *
     * @param ClientInterface[] $clients
     * @throws InvalidClientException
     */
    public function __construct(array $clients)
    {
        foreach ($clients as $client) {
            if (!$client instanceof ClientInterface) {
                throw new InvalidClientException("Invalid client in client list.");
            }

            $this->clients[$client->getHostname()] = $client;
        }
    }

    /**
     * Get the correct client object for that key through consistent hashing
     *
     * @param string $key
     * @return ClientInterface
     * @throws Exception
     */
    protected function getClientFromKey(string $key): ClientInterface
    {
        if (isset($this->keyCache[$key])) {
            return $this->keyCache[$key];
        }

        if ($this->hash === null) {
            $this->hash = new Flexihash();
            foreach ($this->clients as $client) {
                $this->hash->addTarget($client->getHostname());
            }
        }

        $clientHostname = $this->hash->lookup($key);
        $this->keyCache[$key] = $this->clients[$clientHostname];
        return $this->keyCache[$key];
    }

    /**
     * Get random client
     *
     * @return ClientInterface
     */
    protected function getRandomClient(): ClientInterface
    {
        $rndIndex = array_rand($this->clients);
        return $this->clients[$rndIndex];
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getHostname(?string $key = null): string
    {
        if ($key) {
            return $this->getClientFromKey($key)->getHostname($key);
        }
        return implode("-", array_keys($this->clients));
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function put(string $key, $value, bool $prevKv = false, int $lease = 0, bool $ignoreLease = false, bool $ignoreValue = false)
    {
        return $this->getClientFromKey($key)->put($key, $value, $prevKv, $lease, $ignoreLease, $ignoreValue);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function get(string $key)
    {
        return $this->getClientFromKey($key)->get($key);
    }

    /**
     * @param string $prefix
     * @param int $limit
     * @inheritDoc
     * @throws Exception
     */
    public function getWithPrefix(string $prefix, int $limit = 100): Generator
    {
        return $this->getClientFromKey($prefix)->getWithPrefix($prefix, $limit);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function delete(string $key)
    {
        return $this->getClientFromKey($key)->delete($key);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function putIf(string $key, string $value, $compareValue, bool $returnNewValueOnFail = false)
    {
        return $this->getClientFromKey($key)->putIf($key, $value, $compareValue, $returnNewValueOnFail);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function deleteIf(string $key, $compareValue, bool $returnNewValueOnFail = false)
    {
        return $this->getClientFromKey($key)->deleteIf($key, $compareValue, $returnNewValueOnFail);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function txnRequest(array $requestOperations, ?array $failureOperations, array $compare): TxnResponse
    {
        return $this->getRandomClient()->txnRequest($requestOperations, $failureOperations, $compare);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getCompare(string $key, string $value, int $result, int $target): Compare
    {
        return $this->getClientFromKey($key)->getCompare($key, $value, $result, $target);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getGetOperation(string $key): RequestOp
    {
        return $this->getClientFromKey($key)->getGetOperation($key);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getPutOperation(string $key, string $value, int $leaseId = 0): RequestOp
    {
        return $this->getClientFromKey($key)->getPutOperation($key, $value, $leaseId);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function getDeleteOperation(string $key): RequestOp
    {
        return $this->getClientFromKey($key)->getDeleteOperation($key);
    }

    /**
     * @inheritDoc
     */
    public function getLeaseID(int $ttl)
    {
        return $this->getRandomClient()->getLeaseID($ttl);
    }

    /**
     * @inheritDoc
     */
    public function revokeLeaseID(int $leaseID)
    {
        return $this->getRandomClient()->revokeLeaseID($leaseID);
    }

    /**
     * @inheritDoc
     */
    public function refreshLease(int $leaseID)
    {
        return $this->getRandomClient()->refreshLease($leaseID);
    }

    /**
     * Transform TxnResponse into more friendly array
     *
     * @param TxnResponse $txnResponse return value of txnRequest method
     * @param string|null $type returns only chosen type if defined,
     *                          can be one of those: response_range, response_put, response_delete_range, response_txn
     * @param bool $simpleArray return just simple array containing values,
     *                          example: ['value1', 'value2']
     * @return array example: [
     *                          [
     *                           'type' => 'response_range',
     *                           'values' => [
     *                                        'key' => 'key',
     *                                        'value' => '2',
     *                                        'version' => 3
     *                                        ]
     *                           ]
     *                         ]
     */
    public function getResponses(TxnResponse $txnResponse, ?string $type = null, bool $simpleArray = false): array
    {
        return $this->getRandomClient()->getResponses($txnResponse, $type, $simpleArray);
    }
}
