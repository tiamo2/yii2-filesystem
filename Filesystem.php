<?php

namespace tiamo\filesystem;

use Yii;
use yii\helpers\Url;
use yii\base\InvalidConfigException;

class Filesystem extends \yii\base\Component
{
    public $fileClass = 'File';
    public $basePath = '@webroot/files';
    public $baseUrl = '@web/files';
    public $defaultStorage = 'local';
    public $storage = [];
    public $formats = [];
    public $adaptersMap = [];

	protected $defaultAdaptersMap = [
		'local' => [
			'class' => 'League\Flysystem\Adapter\Local',
		],
		'ftp' => [
			'class' => 'League\Flysystem\Adapter\Ftp',
			'required' => ['host']
		],
		'sftp' => [
			'class' => 'League\Flysystem\Sftp\SftpAdapter',
			'required' => ['host', 'username']
		],
		'dropbox' => [
			'class' => 'League\Flysystem\Dropbox\DropboxAdapter',
			'required' => ['token', 'path']
		],
		'gridfs' => [
			'class' => 'League\Flysystem\GridFS\GridFSAdapter',
			'required' => ['server', 'database']
		],
		'awss3' => [
			'class' => 'League\Flysystem\AwsS3v2\AwsS3Adapter',
			'required' => ['key', 'secret', 'bucket']
		],
		'azure' => [
			'class' => 'League\Flysystem\Azure\AzureAdapter',
			'required' => ['accountName', 'accountKey', 'container']
		],
		'copy' => [
			'class' => 'League\Flysystem\Copy\CopyAdapter',
			'required' => ['consumerKey', 'consumerSecret', 'accessToken', 'tokenSecret']
		],
		'rackspace' => [
			'class' => 'League\Flysystem\Rackspace\RackspaceAdapter',
			'required' => ['endpoint', 'username', 'apiKey', 'region', 'container']
		],
		'webdav' => [
			'class' => 'League\Flysystem\WebDAV\WebDAVAdapter',
			'required' => ['baseUri']
		],
	];

	private $_mountManager;

    public function init()
    {
		$this->adaptersMap = array_merge($this->defaultAdaptersMap, $this->adaptersMap);
		$this->basePath = Yii::getAlias($this->basePath);
		$this->baseUrl = Yii::getAlias($this->baseUrl);
		// local filesystem is default prepared
		$this->storage['local'] = [
			'baseUrl' => $this->baseUrl,
			'adapter' => 'local',
		];
		$filesystems = [];
		foreach($this->storage as $name => $config) {
			if (!isset($config['adapter'])) {
				throw new InvalidConfigException('The "adapter" property must be set.');
			}
			$filesystems[$name] = Yii::createObject('League\Flysystem\Filesystem', [
				$this->prepareAdapter($config['adapter'], $config),
			]);
		}
		$this->_mountManager = Yii::createObject('League\Flysystem\MountManager', [$filesystems]);
    }

    /**
     * @param string $name
     * @param array $config
     * @return League\Flysystem\Adapter\AbstractAdapter
     */
    protected function prepareAdapter($name, $config)
    {
		if (!isset($this->adaptersMap[$name])) {
			throw new \Exception(sprintf('Unknown adapter "%s".', $name));
		}
		$adapter = $this->adaptersMap[$name];
		$class = $adapter['class'];
		// check required properties
		if (!empty($adapter['required'])) {
			foreach($adapter['required'] as $prop) {
				if (!isset($config[$prop])) {
					throw new InvalidConfigException(sprintf('The "%s" property must be set.', $prop));
				}
			}
		}
		switch($name) {
			case('local'):
				return Yii::createObject($class, [$this->basePath]);
				break;
			case('dropbox'):
				return Yii::createObject($class, [
					 Yii::createObject('Dropbox\Client', [$config['token'], $config['app']]),
					isset($config['prefix']) ? $config['prefix'] : null
				]);
				break;
			case('ftp'):
				if (isset($config['root'])) {
					$config['root'] = Yii::getAlias($config['root']);
				}
				break;
			case('sftp'):
				if (!isset($config['password']) && !isset($config['privateKey'])) {
					throw new InvalidConfigException('Either "password" or "privateKey" property must be set.');
				}
				if (isset($config['root'])) {
					$config['root'] = Yii::getAlias($config['root']);
				}
				break;
			case('gridfs'):
				return Yii::createObject($class, [
					(new \MongoClient($config['server']))->selectDB($config['database'])->getGridFS()
				]);
				break;
			case('awss3'):
				return Yii::createObject($class, [
					\Aws\S3\S3Client::factory($config),
					$config['bucket'],
					isset($config['prefix']) ? $config['prefix'] : null,
					isset($config['options']) ? $config['options'] : [],
				]);
				break;
			case('azure'):
				return Yii::createObject($class, [
					\WindowsAzure\Common\ServicesBuilder::getInstance()->createBlobService(sprintf(
						'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s',
						base64_encode($config['accountName']),
						base64_encode($config['accountKey'])
					)),
					$config['container']
				]);
				break;
			case('copy'):
				return Yii::createObject($class, [
					new \Barracuda\Copy\API($config['consumerKey'], $config['consumerSecret'], $config['accessToken'], $config['tokenSecret']),
					isset($config['prefix']) ? $config['prefix'] : null,
				]);
				break;
			case('rackspace'):
				return Yii::createObject($class, [
					(new \OpenCloud\Rackspace($config['endpoint'], [
						'username' => $config['username'],
						'apiKey' => $config['apiKey'],
					]))->objectStoreService('cloudFiles', $config['region'])->getContainer($config['container']),
					isset($config['prefix']) ? $config['prefix'] : null,
				]);
				break;
			case('webdav'):
				return Yii::createObject($class, [
					new \Sabre\DAV\Client($config['endpoint']),
					isset($config['prefix']) ? $config['prefix'] : null,
				]);
				break;
			default:
				$class = 'League\Flysystem\Adapter\NullAdapter';
				break;
		}
		
		return Yii::createObject($class, [$config]);
	}

    /**
     * @param UploadedFile $file
     * @param ActiveRecord|array $owner
     * @param string $fs
     * @return File object
     */
    public function store($file, $owner=null, $fs=null)
    {
		if (!empty($file) && !$file->hasError) {
			
			$model = Yii::createObject($this->fileClass);
			$model->setOwner($owner);
			$model->type = $file->type;
			$model->size = $file->size;
			$model->extension = $file->extension;
			$model->storage = $fs ? $fs : $this->defaultStorage;
			if (!isset($this->storage[$model->fs])) {
				throw new \Exception('Unknown filesystem.');
			}
			// saving file to prepare identifier
			if ($model->save()) {
				try {
					$model->path = static::generatePath($model->id, $file->name);
					$model->hash = md5_file($file->tempName);
					if (!$this->has($model->fsPath)) {
						$this->createDir($model->fsDir);
						$stream = fopen($file->tempName, 'r+');
						$this->writeStream($model->fsPath, $stream);
						fclose($stream);
					}
					$model->updateAttributes([
						'hash'=>$model->hash,
						'path'=>$model->path,
					]);
				}
				catch(\Exception $e) {
					$model->delete();
				}
			}
			return $model;
		}
    }

	/**
	 * @param File $file
	 * @param mixed $handler
	 * @param array $params
	 * @param string $prefix
	 * @return boolean New file exists ?
	 */
	public function process($file, $handler, array $params=array(), $prefix=null)
	{
		$path = $file->getFsPath($prefix);
		try {
			// create temporary file
			$tmp = tmpfile();
			// copy file stream contents
			if (stream_copy_to_stream($file->readStream(), $tmp)) {
				$meta = stream_get_meta_data($tmp);
				$params[] = $meta['uri'];
				$params[] = $file;
				$this->write($path, call_user_func_array($handler, $params));
			}
			fclose($tmp);
		}
		catch(\Exception $e) {
		}
		return $this->has($path);
	}

	/**
	 * @param string $id
     * @param string $name
	 * @return string
	 */
	public static function generatePath($id, $name)
	{
		return implode('/', [intval($id/10000), intval($id/1000), $id, $name]);
	}

	/**
	 * @param string $method
     * @param array $parameters
	 * @return mixed
	 */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->_mountManager, $method], $parameters);
    }
}