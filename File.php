<?php

namespace tiamo\yii2-filesystem;

use Yii;
use yii\web\ActiveRecord;

/**
 * This is the model class for table "storage".
 *
 * @property integer $id
 * @property string $fs
 * @property string $path
 * @property string $extension
 * @property string $type
 * @property integer $size
 * @property string $hash
 * @property string $owner_model
 * @property string $owner_id
 * @property string $expired
 * @property string $created
 */
class File extends ActiveRecord
{
    /**
     * @var yii\base\Model
     */
	private $_owner = false;

    /**
     * @inheritdoc
     */
	public function __toString()
	{
		return $this->url;	
	}

    /**
     * @inheritdoc
     */
    public static function fs()
	{
		return Yii::$app->fs;
	}

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%file}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'timestamp' => [
                'class' => 'yii\behaviors\TimestampBehavior',
                'value' => function () { return date("Y-m-d H:i:s"); },
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'created',
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['size', 'owner_id'], 'integer'],
            [['storage', 'extension'], 'string', 'max' => 10],
            [['path', 'format', 'type', 'owner_model'], 'string', 'max' => 255],
            [['hash'], 'string', 'max' => 32],
            [['expired', 'created'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'fs' => 'Filesystem',
            'path' => 'Path',
            'extension' => 'Extension',
            'type' => 'Type',
            'size' => 'Size',
            'hash' => 'Hash',
            'owner_model' => 'Owner',
            'owner_id' => 'Owner id',
            'expired' => 'Expired',
            'created' => 'Created',
        ];
    }

    /**
     * @inheritdoc
     */
	public function setOwner($owner)
	{
		if (!empty($owner)) {
			$this->_owner = is_object($owner) ? $owner : Yii::createObject($owner);
			$this->owner_model = get_class($this->_owner);
			$this->owner_id = $this->_owner->id;
		}
	}

    /**
     * @inheritdoc
     */
	public function getOwner()
	{
		if ($this->_owner===false && $this->owner_model && $this->owner_id) {
			$model = Yii::createObject($this->owner_model);
			if ($model) {
				$this->_owner = $model::findOne($this->owner_id);
			}
		}
		return $this->_owner;
	}

    /**
     * @inheritdoc
     */
	public function getName()
	{
		return basename($this->path);
	}

    /**
     * @inheritdoc
     */
	public function getUrl($prefix=null)
	{
		$fs = static::fs();
		if (!isset($fs->storage[$this->fs])) {
			throw new \Exception(sprintf('Unknown filesystem "%s".', $this->fs));
		}
		$storage = $fs->storage[$this->fs];
		$baseUrl = !empty($storage['baseUrl']) ? $storage['baseUrl'] : '';
		// automatic formating image
		return $baseUrl .'/'. $this->format($prefix)->getPath($prefix);
	}

    /**
     * @inheritdoc
     */
	public function getPath($prefix=null)
	{
		if ($prefix) {
			$prefix .= '_';
		}
		return dirname($this->path) .'/'. $prefix . $this->name;
	}

    /**
     * @inheritdoc
     */
	public function getFsPath($prefix=null)
	{
		return $this->fs . '://' . $this->getPath($prefix);
	}

    /**
     * @inheritdoc
     */
	public function getFsDir()
	{
		return $this->fs . '://' . dirname($this->path);
	}

    /**
     * @inheritdoc
     */
	public function getFs()
	{
		return $this->storage ? $this->storage : 'local';
	}

    /**
     * @inheritdoc
     */
	public function getTempHash()
	{
		return md5($this->created);
	}

    /**
     * @inheritdoc
     */
	public function getIsImage()
	{
		return substr($this->type,0,5) === 'image';
	}

    /**
     * @inheritdoc
     */
    public function getTimestamp()
	{
		return static::fs()->getTimestamp($this->fsPath);
	}

    /**
     * @inheritdoc
     */
    public function getMetadata()
	{
		return static::fs()->getMetadata($this->fsPath);
	}

    /**
     * @inheritdoc
     */
    public function getVisibility()
	{
		return static::fs()->getVisibility($this->fsPath);
	}

    /**
     * @inheritdoc
     */
	public function read()
	{
		return static::fs()->read($this->fsPath);
	}

    /**
     * @inheritdoc
     */
	public function readStream()
	{
		return static::fs()->readStream($this->fsPath);
	}

    /**
     * @inheritdoc
     */
	public function write($contents, array $config = [])
	{
		return static::fs()->write($this->fsPath, $contents, $config);
	}

    /**
     * @inheritdoc
     */
	public function writeStream($resource, array $config = [])
	{
		return static::fs()->writeStream($this->fsPath, $resource, $config);
	}

    /**
     * @inheritdoc
     */
    public function copy($newpath)
	{
		return static::fs()->copy($this->fsPath, $newpath);
	}

    /**
     * @inheritdoc
     */
    public function move($newpath)
	{
		return static::fs()->move($this->fsPath, $newpath);
	}

    /**
     * @inheritdoc
     */
    public function format($prefix)
    {
		if ($prefix) {
			$fs = static::fs();
			$formats = explode(',', $this->format);
			if (!in_array($prefix, $formats)) {
				foreach($fs->formats as $pattern => $handler) {
					if (preg_match($pattern, $prefix, $matches)) {
						$params = array_splice($matches,1);
						if ($fs->process($this, $handler, $params, $prefix)) {
							$formats[] = $prefix;
							$formats = array_unique($formats);
							$this->updateAttributes(['format'=>implode(',', $formats)]);
						}
					}
				}
			}
		}
		return $this;
    }

	/**
     * @inheritdoc
     */
	public function afterDelete()
	{
		if (!empty($this->path)) {
			static::fs()->deleteDir($this->fsDir);
		}
		parent::afterDelete();
	}

    /**
     * @inheritdoc
     */
	public function afterSave($insert, $changedAttributes)
	{
		// update owner counters
		if ($this->owner && $this->owner->hasAttribute('file_count')) {
			$this->owner->updateAttributes(['file_count'=>$this->owner->file_count+1]);
		}
		parent::afterSave($insert, $changedAttributes);
	}
}
