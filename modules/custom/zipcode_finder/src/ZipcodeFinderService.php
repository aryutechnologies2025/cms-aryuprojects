<?php

namespace Drupal\zipcode_finder;

use Drupal\Core\Database\Connection;
use Drupal\zipcode_finder\Entity\ZipcodeFinder;
use Drupal\zipcode_finder\Entity\ZipcodeFinderLog;

/**
 * Service related to the zipcode finder
 */
class ZipcodeFinderService
{

    /**
     * The database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $connection;

    protected $logTable = 'zipcode_log';

    /**
     * Constructs a ZipcodeFinderService object.
     *
     * @param \Drupal\Core\Database\Connection $connection
     *   The database connection.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }


    public function findByZipcode($zip) {
        return ZipcodeFinder::findByZipcode($zip);
    }

    public function getLogByZip($zip) {
        return ZipcodeFinderLog::load($zip);
    }

    public static function normalizeZipcode($zip)
    {
        $zip = (string) $zip;
        $zip = preg_replace('#\s#', '', strtoupper($zip));
        $zip = trim($zip);
        return $zip;
    }

    public function logZipcode($zip) {
        $zip = static::normalizeZipcode($zip);
        $logItem = $this->getLogByZip($zip);
        if(!$logItem instanceof ZipcodeFinderLog) {
            $logItem = ZipcodeFinderLog::create([
                'zipcode' => $zip,
            ]);
        }
        $currentCount = $logItem->count->value ?? 0;
        $logItem->set('count', $currentCount + 1);
        $logItem->save();
        return $logItem;
    }

}
