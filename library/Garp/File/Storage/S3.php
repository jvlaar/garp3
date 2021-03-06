<?php
/**
 * Storage and retrieval of user uploads, from Amazon's S3 CDN.
 * @author David Spreekmeester | Grrr.nl
 * @package Garp
 */
class Garp_File_Storage_S3 implements Garp_File_Storage_Protocol {
    /**
     * Store the configuration so that it is built just once per session.
     * @var Boolean
     */
    protected $_config = array();

    protected $_requiredS3ConfigParams = array('apikey', 'secret', 'bucket');

    /** @var Zend_Service_Amazon_S3 $_api */
    protected $_api;

    protected $_apiInitialized = false;

    protected $_bucketExists = false;

    protected $_knownMimeTypes = array(
        'js' => 'text/javascript',
        'css' => 'text/css',
        'html' => 'text/html',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml'
    );

    /** @const Int TIMEOUT Number of seconds after which to timeout the S3 action. Should support uploading large (20mb) files. */
    const TIMEOUT = 400;


    /**
     * @param Zend_Config $config The 'cdn' section from application.ini, containing S3 and general CDN configuration.
     * @param String $path Relative path to the location of the stored file, excluding trailing slash but always preceded by one.
     * @param Boolean $keepalive Wether to keep the socket open
     */
    public function __construct(Zend_Config $config, $path = '/', $keepalive = false) {
        $this->_setConfigParams($config);

        if ($path) {
            $this->_config['path'] = $path;
        }

        $this->_config['keepalive'] = $keepalive;
    }


    public function setPath($path) {
        $this->_config['path'] = $path;
    }


    public function exists($filename) {
        $this->_initApi();
        return $this->_api->isObjectAvailable($this->_config['bucket'] . $this->_getUri($filename));
    }


    /** Fetches the url to the file, suitable for public access on the web. */
    public function getUrl($filename) {
        $this->_verifyPath();
        if ($this->_config['ssl'] && $this->_config['region']) {
            return 'https://s3-' . $this->_config['region'] . '.amazonaws.com/' .
                $this->_config['bucket'] . $this->_config['path'] . '/' . $filename;
        }
        return 'http://' . $this->_config['domain'] . $this->_config['path'] . '/' . $filename;
    }


    /** Fetches the file data. */
    public function fetch($filename) {
        // return fopen($this->getUrl($filename), 'r');
        $this->_initApi();
        $obj = $this->_api->getObject($this->_config['bucket'].$this->_getUri($filename));
        if ($this->_config['gzip'] && $this->_gzipIsAllowedForFilename($filename)) {
            $unzipper = new Garp_File_Unzipper($obj);
            $obj = $unzipper->getUnpacked();
        }
        return $obj;
    }


    /** Lists all files in the upload directory. */
    public function getList() {
        $this->_initApi();
        $this->_verifyPath();

        // strip off preceding slash, add trailing one.
        $path = substr($this->_config['path'], 1) . '/';
        $objects = $this->_api->getObjectsByBucket($this->_config['bucket'], array('prefix' => $path));

        return $objects;
    }


    /** Returns mime type of given file. */
    public function getMime($filename) {
        $this->_initApi();
        $info = $this->_api->getInfo($this->_config['bucket'].$this->_getUri($filename));

        if (array_key_exists('type', $info)) {
            return $info['type'];
        } else throw new Exception("Could not retrieve mime type of {$filename}.");
    }


    public function getSize($filename) {
        $this->_initApi();
        $info = $this->_api->getInfo($this->_config['bucket'].$this->_getUri($filename));

        if (
            array_key_exists('size', $info) &&
            is_numeric($info['size'])
        ) {
            return $info['size'];
        } else throw new Exception("Could not retrieve size of {$filename}.");
    }


    public function getEtag($filename) {
        $this->_initApi();
        $path = $this->_config['bucket'] . $this->_getUri($filename);
        $info = $this->_api->getInfo($path);

        if (array_key_exists('etag', $info)) {
            $info['etag'] = str_replace('"', '', $info['etag']);
            return $info['etag'];
        } else throw new Exception("Could not retrieve eTag of {$filename}.");
    }


    /** Returns last modified time of file, as a Unix timestamp. */
    public function getTimestamp($filename) {
        $this->_initApi();
        $info = $this->_api->getInfo($this->_config['bucket'].$this->_getUri($filename));

        if (array_key_exists('mtime', $info)) {
            return $info['mtime'];
        } else throw new Exception("Could not retrieve timestamp of {$filename}.");
    }


    /**
    * @param String $filename
    * @param String $data               Binary file data
    * @param Boolean $overwrite         Whether to overwrite this file, or create a unique name
    * @param Boolean $formatFilename    Whether to correct the filename, f.i. ensuring lowercase characters.
    * @param Boolean $initialize
    * @return String                    Destination filename.
    */
    public function store($filename, $data, $overwrite = false, $formatFilename = true) {
        $this->_initApi();
        $this->_createBucketIfNecessary();

        if ($formatFilename)
            $filename = Garp_File::formatFilename($filename);

        if (!$overwrite) {
            while ($this->exists($filename)) {
                $filename = Garp_File::getCumulativeFilename($filename);
            }
        }

        $path = $this->_config['bucket'] . $this->_getUri($filename);
        $meta = array(
            Zend_Service_Amazon_S3::S3_ACL_HEADER => Zend_Service_Amazon_S3::S3_ACL_PUBLIC_READ,
        );

        if (false !== strpos($filename, '.')) {
            $ext = substr($filename, strrpos($filename, '.')+1);
            if (array_key_exists($ext, $this->_knownMimeTypes)) {
                $mime = $this->_knownMimeTypes[$ext];
            } else {
                $finfo = new finfo(FILEINFO_MIME);
                $mime  = $finfo->buffer($data);
            }
        } else {
            $finfo = new finfo(FILEINFO_MIME);
            $mime  = $finfo->buffer($data);
        }
        $meta[Zend_Service_Amazon_S3::S3_CONTENT_TYPE_HEADER] = $mime;
        if ($this->_config['gzip'] && $this->_gzipIsAllowedForFilename($filename)) {
            $meta['Content-Encoding'] = 'gzip';
            $data = gzencode($data);
        }

        if ($this->_api->putObject(
            $path,
            $data,
            $meta
        )) {
            return $filename;
        }

        return false;
    }


    public function remove($filename) {
        $this->_initApi();
        return $this->_api->removeObject($this->_config['bucket'].$this->_getUri($filename));
    }


    /** Returns the uri for internal Zend_Service_Amazon_S3 use. */
    protected function _getUri($filename) {
        // return $this->_bucket.$this->_path.'/'.$filename;
        //  bucket should no longer be prefixed to the path, or perhaps this never should have been done in the first place.
        //  david, 2012-01-30
        $this->_verifyPath();
        $p = $this->_config['path'];

        return
            $p
            .($p[strlen($p)-1] === '/' ? null : '/')
            .$filename
        ;
    }


    protected function _createBucketIfNecessary() {
        if (!$this->_bucketExists) {
            if (!$this->_api->isBucketAvailable($this->_config['bucket'])) {
                $this->_api->createBucket($this->_config['bucket']);
            }

            $this->_bucketExists = true;
        }
    }


    protected function _validateConfig(Zend_Config $config) {
        foreach ($this->_requiredS3ConfigParams as $reqPar) {
            if (!$config->s3->{$reqPar}) {
                throw new Exception("'cdn.s3.{$reqPar}' must be set in application.ini.");
            }
        }
    }


    protected function _setConfigParams(Zend_Config $config) {
        if (!$this->_config) {
            $this->_validateConfig($config);

            $this->_config['apikey'] = $config->s3->apikey;
            $this->_config['secret'] = $config->s3->secret;
            $this->_config['bucket'] = $config->s3->bucket;
            $this->_config['domain'] = !empty($config->domain) ? $config->domain : null;
            $this->_config['gzip']   = $config->gzip;
            $this->_config['ssl']    = $config->ssl;
            $this->_config['region'] = $config->s3->region;
            $this->_config['gzip_exceptions'] = (array)$config->gzip_exceptions;
        }
    }


    protected function _initApi() {
        if (!$this->_apiInitialized) {
            @ini_set('max_execution_time', self::TIMEOUT);
            @set_time_limit(self::TIMEOUT);
            if (!$this->_api) {
                $this->_api = new Garp_Service_Amazon_S3(
                    $this->_config['apikey'],
                    $this->_config['secret']
                );
            }

            $this->_api->getHttpClient()->setConfig(array(
                'timeout' => self::TIMEOUT,
                'keepalive' => $this->_config['keepalive']
            ));

            $this->_apiInitialized = true;
        }
    }


    protected function _verifyPath() {
        if (!$this->_config['path']) {
            throw new Exception("There is not path configured, please do this with setPath().");
        }
    }

    protected function _gzipIsAllowedForFilename($filename) {
        $ext = substr($filename, strrpos($filename, '.')+1);
        if (!$ext) {
            return true;
        }
        return !in_array($ext, $this->_getGzipExceptions());
    }

    protected function _getGzipExceptions() {
        return $this->_config['gzip_exceptions'];
    }

}
