<?php
/**
 * Interface to the MySQL Improved extension (PDO)
 */

declare(strict_types=1);

namespace PhpMyAdmin\Dbal;

use PDO;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Query\Utilities;

use function __;
use function defined;
use function mysqli_connect_errno;
use function mysqli_connect_error;
use function mysqli_get_client_info;
use function mysqli_init;
use function mysqli_report;
use function sprintf;
use function stripos;
use function trigger_error;

use const E_USER_ERROR;
use const E_USER_WARNING;
use const MYSQLI_CLIENT_COMPRESS;
use const MYSQLI_CLIENT_SSL;
use const MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
use const MYSQLI_OPT_LOCAL_INFILE;
use const MYSQLI_OPT_SSL_VERIFY_SERVER_CERT;
use const MYSQLI_REPORT_OFF;
use const MYSQLI_STORE_RESULT;
use const MYSQLI_USE_RESULT;

/**
 * Interface to the MySQL Improved extension (PDO)
 */
class DbiPdoTaos implements DbiExtension
{
    /**
     * connects to the database server
     *
     * @param string $user     mysql user name
     * @param string $password mysql user password
     * @param array  $server   host/port/socket/persistent
     *
     * @return PDO|bool false on error or a PDO object on success
     */
    public function connect($user, $password, array $server)
    {
        if ($server) {
            $server['host'] = empty($server['host'])
                ? 'localhost'
                : $server['host'];

            $server['port'] = empty($server['port'])
                ? 6030
                : $server['port'];
        }


        if ($GLOBALS['cfg']['PersistentConnections']) {
            $host = 'p:' . $server['host'];
        } else {
            $host = $server['host'];
        }


        $dbh = new PDO("taos:host={$host};port={$server['port']}", $user, $password);

        return $dbh;
    }

    /**
     * selects given database
     *
     * @param string|DatabaseName $databaseName database name to select
     * @param PDO              $link         the PDO object
     */
    public function selectDb($databaseName, $link): bool
    {
        $link->query("use $databaseName");
        return true;
    }

    /**
     * runs a query and returns the result
     *
     * @param string $query   query to execute
     * @param PDO $link    PDO object
     * @param int    $options query options
     *
     * @return PdoTaosResult
     */
    public function realQuery(string $query, $link, int $options)
    {
        $result = $link->query($query, PDO::FETCH_ASSOC);
        if ($result === false) {
            return false;
        }

        return new PdoTaosResult($result);
    }

    /**
     * Run the multi query and output the results
     *
     * @param PDO $link  PDO object
     * @param string $query multi query statement to execute
     */
    public function realMultiQuery($link, $query): bool
    {
        return $link->multi_query($query);
    }

    /**
     * Check if there are any more query results from a multi query
     *
     * @param PDO $link the PDO object
     */
    public function moreResults($link): bool
    {
        return $link->nextRowset();
    }

    /**
     * Prepare next result from multi_query
     *
     * @param PDO $link the PDO object
     */
    public function nextResult($link): bool
    {
        return $link->next_result();
    }

    /**
     * Store the result returned from multi query
     *
     * @param PDO $link the PDO object
     *
     * @return MysqliResult|false false when empty results / result set when not empty
     */
    public function storeResult($link)
    {
        $result = $link->store_result();

        return $result === false ? false : new PdoTaosResult($result);
    }

    /**
     * Returns a string representing the type of connection used
     *
     * @param PDO $link PDO link
     *
     * @return string type of connection used
     */
    public function getHostInfo($link)
    {
        return $link->getServerInfo();
    }

    /**
     * Returns the version of the MySQL protocol used
     *
     * @param PDO $link PDO link
     *
     * @return string version of the MySQL protocol used
     */
    public function getProtoInfo($link)
    {
        // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
        return $link->protocol_version;
    }

    /**
     * returns a string that represents the client library version
     *
     * @param PDO $link PDO link
     *
     * @return string MySQL client library version
     */
    public function getClientInfo($link)
    {
        return $link->getServerInfo();
    }

    /**
     * Returns last error message or an empty string if no errors occurred.
     *
     * @param PDO|false|null $link mysql link
     */
    public function getError($link): string
    {
        $GLOBALS['errno'] = 0;

        $error_number = 0;
        $error_message = '';
        if ($link !== null && $link !== false) {
            $error_number = $link->errorCode();
            $error_message = $link->errorInfo()[2];
        }

        if ($error_number === 0 || $error_message == '') {
            return '';
        }

        // keep the error number for further check after
        // the call to getError()
        $GLOBALS['errno'] = $error_number;

        var_dump($error_number, $error_message);
        return Utilities::formatError($error_number, $error_message);
    }

    /**
     * returns the number of rows affected by last query
     *
     * @param PDO $link the PDO object
     *
     * @return int|string
     * @psalm-return int|numeric-string
     */
    public function affectedRows($link)
    {
        // phpcs:ignore Squiz.NamingConventions.ValidVariableName.MemberNotCamelCaps
        return 0;
        return $link->rowCount();
    }

    /**
     * returns properly escaped string for use in MySQL queries
     *
     * @param PDO $link   database link
     * @param string $string string to be escaped
     *
     * @return string a MySQL escaped string
     */
    public function escapeString($link, $string)
    {
        return $link->real_escape_string($string);
    }

    /**
     * Prepare an SQL statement for execution.
     *
     * @param PDO $link  database link
     * @param string $query The query, as a string.
     *
     * @return PDO|false A statement object or false.
     */
    public function prepare($link, string $query)
    {
        return $link->prepare($query);
    }
}
