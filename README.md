openwall PasswordHash by yii2
=============================
Portable PHP password hashing framework in yii2
http://www.openwall.com/phpass/

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist byflav/yii2-phpass "*"
```

or add

```
"byflav/yii2-phpass": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :


1 setup component in config file app:

		'components' => [
			'security' => [
				'class' => 'byflav\phpass\Security',
				'iterationCountLog2' => 8,     // integer > 3 and < 31 ; default 8;
				'portableHashes' 	 => false, // boolean; default false;
			],
		]

2 example use:

		#create hash:
		$hash = Yii::$app->getSecurity()->generatePasswordHash('lorem ipsum');

		#validate hash: (return boolean)
		return Yii::$app->getSecurity()->validatePassword('lorem ipsum', $hash);

```