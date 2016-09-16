<?php
	namespace byflav\phpass;
	/*
		Extends \yii\base\Security and use PasswordHash (http://www.openwall.com/phpass/)
		setup component in config file app

				'components' => [
					'security' => [
						'class' => 'byflav\phpass\Security',
						'iterationCountLog2' => 8,     // integer > 3 and < 31 ; default 8;
						'portableHashes' 	 => false, // boolean; default false;
					],
				]

		#create hash:
		$hash = Yii::$app->getSecurity()->generatePasswordHash('lorem ipsum');

		#validate hash: (return boolean)
		return Yii::$app->getSecurity()->validatePassword('lorem ipsum', $hash);

	*/
	class Security extends \yii\base\Security
	{
		use PasswordHash;

		public function generatePasswordHash($password)
		{
			return $this->hashPassword($password);
		}

		public function validatePassword($password, $hash)
		{
			return $this->checkPassword($password, $hash);
		}
	}