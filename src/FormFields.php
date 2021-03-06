<?php
namespace Codeception\Module;

//use Codeception\Module as CodeceptionModule;
use Jasny\DotKey;
use Symfony\Component\Yaml\Yaml;

class FormFields extends Codeception\Module
{
    const PARAM_KEY__DATA_PATH_TPL = 'dataPathTpl';
    const PARAM_KEY__FILES         = 'files';
    /**
     * @example
     *
     * modules:
     *     enabled:
     *         DataProvider
     *         ... # some other modules
     *     config:
     *         DataProvider:
     *             dataPathTpl: '{root}/tests/_data/{file}'
     *             files:
     *                 - common.yml
     *                 - dev.yml
     */
    protected $requiredFields = [
        self::PARAM_KEY__DATA_PATH_TPL,
        self::PARAM_KEY__FILES,
    ];
    /**
     * Contains all provided data.
     * @var DotKey
     */
    protected $providedData = null;
    /**
     * Retrieve data from provided YML files.
     *
     * @example
     *
     * // php code
     * $myVar = $I->getValue('headers.nonExisting', 'application/json');
     * echo $myVar; // 'application/json'
     *
     * // YML file
     * headers:
     *     contentType: 'application/json'
     *     accept: 'text/html'
     *
     * @param  string $keyName Key name in provided data. Use dot char to point to nested items.
     * @param  mixed  $default Returned, if given {$keyName} does not exist.
     * @return mixed
     */
    public function getValue($keyName, $default = null)
    {
        if (! $this->providedData->exists($keyName)) {
            return $default;
        }
        return $this->providedData->get($keyName);
    }
    /**
     * Allowes to run test using a list of cases.
     *
     * @example
     *
     * // php code
     * $I->iterateOver('users.admins', function ($item, $index) use ($I) {
     *     $userId = $index + 1;
     *     $I->sendGET("users/{$userId}");
     *     $I->seeResponseContainsJson([
     *         'id'        => $userId,
     *         'is_admin'  => 1,
     *         'email'     => $item['email'],
     *         'full_name' => $item['fullName'],
     *     ]);
     * });
     *
     * // YML file
     * users:
     *     admins:
     *         0:
     *             email: John(at)example.com
     *             fullName: John Example
     *         1:
     *             email: two(at)gmail.com
     *             fullName: Tom The Second
     *
     * // or (this will also work, even it is not an ordered list)
     * users:
     *     admins:
     *         -
     *             email: mark(at)gmail.com
     *             fullName: Mark Whaleberg
     *
     * @param  string   $keyName Key name in provided data. Use dot char to point to nested items.
     * @param  callable $callback
     *
     * @throws ModuleException
     */
    public function iterateOver($keyName, callable $callback)
    {
        $iterator = $this->getIterator($keyName);
        if (empty($iterator)) {
            $msg = 'No data provided with the following alias: ' . $keyName;
            throw new ModuleException(get_class($this), $msg);
        }
        $this->debugSection('Iteration key name', $keyName);
        $this->debugSection('Iteration data', $iterator);
        foreach ($iterator as $index => $item) {
            call_user_func($callback, $item, $index);
        }
    }
    public function _initialize()
    {
        $this->loadProvidedData();
    }
    /**
     * Loads data from a YML "file" (of available).
     */
    protected function loadProvidedData()
    {
        $combinedData = [];
        foreach ($this->getFilesPaths() as $path) {
            $ymlContent   = file_get_contents($this->getFullPath($path));
            $data         = Yaml::parse($ymlContent);
            $combinedData = array_replace_recursive($combinedData, $data);
            $this->debugSection('Data file loaded', $path);
        }
        $this->providedData = new DotKey($combinedData, true);
    }
    /**
     * Overridden to check/validate required fields. Use --debug flag for details in case of any problem.
     */
    protected function validateConfig()
    {
        parent::validateConfig();
        $paths = $this->getFilesPaths();
        if (empty($paths)) {
            $msg = "\nPlease, update the configuration and set 'files' field.\n\n";
            throw new ModuleConfigException(get_class($this), $msg);
        }
        foreach ($paths as $path) {
            $fullPath = $this->getFullPath($path);
            $this->debugSection('Data file check - path', $path);
            if (! file_exists($fullPath) || ! is_readable($fullPath)) {
                $msg = "\nData file does not exist, or is not readable: {$fullPath}.\n\n";
                throw new ModuleConfigException(get_class($this), $msg);
            } else {
                $this->debugSection('Data file check - status', 'OK');
            }
        }
    }
    /**
     * @return array
     */
    protected function getFilesPaths()
    {
        $paths = $this->_getConfig(self::PARAM_KEY__FILES);
        if (is_string($paths)) {
            $paths = [ $paths ];
        }
        $unique = array_unique($paths);
        return array_reverse($unique);
    }
    /**
     * @param  string $filePath
     * @return string
     */
    protected function getFullPath($filePath)
    {
        $filePath = strval($filePath);
        $filePath = str_replace('\\', '/', $filePath);
        $root     = str_replace('\\', '/', getcwd());
        $template = $this->_getConfig(self::PARAM_KEY__DATA_PATH_TPL);
        return strtr($template, [
            '{root}' => rtrim($root, '/'),
            '{file}' => trim($filePath, '/'),
        ]);
    }
    /**
     * @param  string $keyName
     * @return array
     */
    protected function getIterator($keyName)
    {
        $iterator = $this->getValue($keyName);
        if (is_null($iterator) || ! is_array($iterator)) {
            return [];
        }
        if ($this->isAssoc($iterator)) {
            return $iterator;
        }
        return count($iterator) === 0 ? [ $iterator ] : $iterator;
    }
    /**
     * @param  array   $data
     * @return boolean
     */
    protected function isAssoc(array $data)
    {
        $seqOnly = range(0, count($data) - 1);
        return array_keys($data) !== $seqOnly;
    }
}