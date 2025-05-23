<?php

/**
 * Joomla! Content Management System
 *
 * @copyright  (C) 2012 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Cache\Storage;

use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Cache\CacheStorage;
use Joomla\CMS\Cache\Exception\CacheConnectingException;
use Joomla\CMS\Factory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Memcached cache storage handler
 *
 * @link   https://www.php.net/manual/en/book.memcached.php
 * @since  3.0.0
 */
class MemcachedStorage extends CacheStorage
{
    /**
     * Memcached connection object
     *
     * @var    \Memcached
     * @since  3.0.0
     */
    protected static $_db = null;

    /**
     * Payload compression level
     *
     * @var    integer
     * @since  3.0.0
     */
    protected $_compress = 0;

    /**
     * Constructor
     *
     * @param   array  $options  Optional parameters.
     *
     * @since   3.0.0
     */
    public function __construct($options = [])
    {
        parent::__construct($options);

        $this->_compress = Factory::getApplication()->get('memcached_compress', false) ? \Memcached::OPT_COMPRESSION : 0;

        if (static::$_db === null) {
            $this->getConnection();
        }
    }

    /**
     * Create the Memcached connection
     *
     * @return  void
     *
     * @since   3.0.0
     * @throws  \RuntimeException
     */
    protected function getConnection()
    {
        if (!static::isSupported()) {
            throw new \RuntimeException('Memcached Extension is not available');
        }

        $app = Factory::getApplication();

        $host = $app->get('memcached_server_host', 'localhost');
        $port = $app->get('memcached_server_port', 11211);


        // Create the memcached connection
        if ($app->get('memcached_persist', true)) {
            static::$_db = new \Memcached($this->_hash);
            $servers     = static::$_db->getServerList();

            if ($servers && ($servers[0]['host'] != $host || $servers[0]['port'] != $port)) {
                static::$_db->resetServerList();
                $servers = [];
            }

            if (!$servers) {
                static::$_db->addServer($host, $port);
            }
        } else {
            static::$_db = new \Memcached();
            static::$_db->addServer($host, $port);
        }

        static::$_db->setOption(\Memcached::OPT_COMPRESSION, $this->_compress);

        $stats  = static::$_db->getStats();
        $result = !empty($stats["$host:$port"]) && $stats["$host:$port"]['pid'] > 0;

        if (!$result) {
            // Null out the connection to inform the constructor it will need to attempt to connect if this class is instantiated again
            static::$_db = null;

            throw new CacheConnectingException('Could not connect to memcached server');
        }
    }

    /**
     * Get a cache_id string from an id/group pair
     *
     * @param   string  $id     The cache data id
     * @param   string  $group  The cache data group
     *
     * @return  string   The cache_id string
     *
     * @since   1.7.0
     */
    protected function _getCacheId($id, $group)
    {
        $prefix   = Cache::getPlatformPrefix();
        $length   = \strlen($prefix);
        $cache_id = parent::_getCacheId($id, $group);

        if ($length) {
            // Memcached use suffix instead of prefix
            $cache_id = substr($cache_id, $length) . strrev($prefix);
        }

        return $cache_id;
    }

    /**
     * Check if the cache contains data stored by ID and group
     *
     * @param   string  $id     The cache data ID
     * @param   string  $group  The cache data group
     *
     * @return  boolean
     *
     * @since   3.7.0
     */
    public function contains($id, $group)
    {
        static::$_db->get($this->_getCacheId($id, $group));

        return static::$_db->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    /**
     * Get cached data by ID and group
     *
     * @param   string   $id         The cache data ID
     * @param   string   $group      The cache data group
     * @param   boolean  $checkTime  True to verify cache time expiration threshold
     *
     * @return  mixed  Boolean false on failure or a cached data object
     *
     * @since   3.0.0
     */
    public function get($id, $group, $checkTime = true)
    {
        return static::$_db->get($this->_getCacheId($id, $group));
    }

    /**
     * Get all cached data
     *
     * @return  mixed  Boolean false on failure or a cached data object
     *
     * @since   3.0.0
     */
    public function getAll()
    {
        $keys   = static::$_db->get($this->_hash . '-index');
        $secret = $this->_hash;

        $data = [];

        if (\is_array($keys)) {
            foreach ($keys as $key) {
                if (empty($key)) {
                    continue;
                }

                $namearr = explode('-', $key->name);

                if ($namearr !== false && $namearr[0] == $secret && $namearr[1] === 'cache') {
                    $group = $namearr[2];

                    if (!isset($data[$group])) {
                        $item = new CacheStorageHelper($group);
                    } else {
                        $item = $data[$group];
                    }

                    $item->updateSize($key->size);

                    $data[$group] = $item;
                }
            }
        }

        return $data;
    }

    /**
     * Store the data to cache by ID and group
     *
     * @param   string  $id     The cache data ID
     * @param   string  $group  The cache data group
     * @param   string  $data   The data to store in cache
     *
     * @return  boolean
     *
     * @since   3.0.0
     */
    public function store($id, $group, $data)
    {
        $cache_id = $this->_getCacheId($id, $group);

        if (!$this->lockindex()) {
            return false;
        }

        $index = static::$_db->get($this->_hash . '-index');

        if (!\is_array($index)) {
            $index = [];
        }

        $tmparr       = new \stdClass();
        $tmparr->name = $cache_id;
        $tmparr->size = \strlen($data);

        $index[] = $tmparr;
        static::$_db->set($this->_hash . '-index', $index, 0);
        $this->unlockindex();

        static::$_db->set($cache_id, $data, $this->_lifetime);

        return true;
    }

    /**
     * Remove a cached data entry by ID and group
     *
     * @param   string  $id     The cache data ID
     * @param   string  $group  The cache data group
     *
     * @return  boolean
     *
     * @since   3.0.0
     */
    public function remove($id, $group)
    {
        $cache_id = $this->_getCacheId($id, $group);

        if (!$this->lockindex()) {
            return false;
        }

        $index = static::$_db->get($this->_hash . '-index');

        if (\is_array($index)) {
            foreach ($index as $key => $value) {
                if ($value->name == $cache_id) {
                    unset($index[$key]);
                    static::$_db->set($this->_hash . '-index', $index, 0);
                    break;
                }
            }
        }

        $this->unlockindex();

        return static::$_db->delete($cache_id);
    }

    /**
     * Clean cache for a group given a mode.
     *
     * group mode    : cleans all cache in the group
     * notgroup mode : cleans all cache not in the group
     *
     * @param   string  $group  The cache data group
     * @param   string  $mode   The mode for cleaning cache [group|notgroup]
     *
     * @return  boolean
     *
     * @since   3.0.0
     */
    public function clean($group, $mode = null)
    {
        if (!$this->lockindex()) {
            return false;
        }

        $index = static::$_db->get($this->_hash . '-index');

        if (\is_array($index)) {
            $prefix = $this->_hash . '-cache-' . $group . '-';

            foreach ($index as $key => $value) {
                if (str_starts_with($value->name, $prefix) xor $mode !== 'group') {
                    static::$_db->delete($value->name);
                    unset($index[$key]);
                }
            }

            static::$_db->set($this->_hash . '-index', $index, 0);
        }

        $this->unlockindex();

        return true;
    }

    /**
     * Flush all existing items in storage.
     *
     * @return  boolean
     *
     * @since   3.6.3
     */
    public function flush()
    {
        if (!$this->lockindex()) {
            return false;
        }

        return static::$_db->flush();
    }

    /**
     * Test to see if the storage handler is available.
     *
     * @return  boolean
     *
     * @since   3.0.0
     */
    public static function isSupported()
    {
        /*
         * GAE and HHVM have both had instances where Memcached the class was defined but no extension was loaded.
         * If the class is there, we can assume support.
         */
        return class_exists('Memcached');
    }

    /**
     * Lock cached item
     *
     * @param   string   $id        The cache data ID
     * @param   string   $group     The cache data group
     * @param   integer  $locktime  Cached item max lock time
     *
     * @return  mixed  Boolean false if locking failed or an object containing properties lock and locklooped
     *
     * @since   3.0.0
     */
    public function lock($id, $group, $locktime)
    {
        $returning             = new \stdClass();
        $returning->locklooped = false;

        $looptime = $locktime * 10;

        $cache_id = $this->_getCacheId($id, $group);

        $data_lock = static::$_db->add($cache_id . '_lock', 1, $locktime);

        if ($data_lock === false) {
            $lock_counter = 0;

            // Loop until you find that the lock has been released.
            // That implies that data get from other thread has finished.
            while ($data_lock === false) {
                if ($lock_counter > $looptime) {
                    break;
                }

                usleep(100);
                $data_lock = static::$_db->add($cache_id . '_lock', 1, $locktime);
                $lock_counter++;
            }

            $returning->locklooped = true;
        }

        $returning->locked = $data_lock;

        return $returning;
    }

    /**
     * Unlock cached item
     *
     * @param   string  $id     The cache data ID
     * @param   string  $group  The cache data group
     *
     * @return  boolean
     *
     * @since   3.0.0
     */
    public function unlock($id, $group = null)
    {
        $cache_id = $this->_getCacheId($id, $group) . '_lock';
        return static::$_db->delete($cache_id);
    }

    /**
     * Lock cache index
     *
     * @return  boolean
     *
     * @since   3.0.0
     */
    protected function lockindex()
    {
        $looptime  = 300;
        $data_lock = static::$_db->add($this->_hash . '-index_lock', 1, 30);

        if ($data_lock === false) {
            $lock_counter = 0;

            // Loop until you find that the lock has been released.  that implies that data get from other thread has finished
            while ($data_lock === false) {
                if ($lock_counter > $looptime) {
                    return false;
                }

                usleep(100);
                $data_lock = static::$_db->add($this->_hash . '-index_lock', 1, 30);
                $lock_counter++;
            }
        }

        return true;
    }

    /**
     * Unlock cache index
     *
     * @return  boolean
     *
     * @since   3.0.0
     */
    protected function unlockindex()
    {
        return static::$_db->delete($this->_hash . '-index_lock');
    }
}
