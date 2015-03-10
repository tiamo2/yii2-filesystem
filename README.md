Yii2 filesystem
============
Yii2 filesystem

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist tiamo/yii2-filesystem "*"
```

or add

```
"tiamo/yii2-filesystem": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :

```php
	'fs' => [
		'class' => 'tiamo\yii2-filesystem\Filesystem',
		'formats' => [
			'/w([0-9]+)h([0-9]+)/is' => function($w, $h, $path, $file){
				if ($file->isImage) {
					$class = '\yii\imagine\Image';
					$class::$driver = [$class::DRIVER_GD2];
					$thumbnail = $class::thumbnail($path, $w, $h);
					return $thumbnail->get($file->extension);
				}
			},
		],
		'storage' => [
			's1' => [
				'baseUrl' => 'http://s1.site.com',
				'adapter' => 'ftp',
				'host' => '127.0.0.1',
				'root' => 'files'
			],
		],
	],

```