<?php
	#
	# Portable PHP password hashing framework.
	#
	# Version 0.3 / genuine.
	#
	# Written by Solar Designer <solar at openwall.com> in 2004-2006 and placed in
	# the public domain.  Revised in subsequent years, still public domain.
	#
	# There's absolutely no warranty.
	#
	# The homepage URL for this framework is:
	#
	#	http://www.openwall.com/phpass/
	#
	# Please be sure to update the Version line if you edit this file in any way.
	# It is suggested that you leave the main version number intact, but indicate
	# your project name (after the slash) and add your own revision information.
	#
	# Please do not change the "private" password hashing method implemented in
	# here, thereby making your hashes incompatible.  However, if you must, please
	# change the hash type identifier (the "$P$") to something different.
	#
	# Obviously, since this code is in the public domain, the above are not
	# requirements (there can be none), but merely suggestions.
	#
	/*
		overload byFlav
	*/
	namespace byFlav\phpass;
	use Yii;
	use \Exception;

	trait PasswordHash
	{
		public $portableHashes;
		public $iterationCountLog2;
		private $_itoa64;
		private $_randomState;

		public function init()
		{
			parent::init();

			$this->_itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

			if($this->iterationCountLog2 < 4 || $this->iterationCountLog2 > 31 )
				$this->iterationCountLog2 = 8;

			$this->portableHashes  = ($this->portableHashes === true) ? $this->portableHashes : false;
			$this->_randomState    = (function_exists('getmypid'))   ? (microtime() . getmypid()) : microtime();
		}

		private function getRandomBytes($count)
		{
			$output = '';
			try{
				$fh     = fopen('/dev/urandom', 'rb');
				$output = fread($fh, $count);
				fclose($fh);
			}
			catch(Exception $e)
			{
				yii::warning($e->getMessage());
			}

			if (strlen($output) < $count) {
				$output = '';
				for ($i = 0; $i < $count; $i += 16) {
					$this->_randomState =
					    md5(microtime() . $this->_randomState);
					$output .=
					    pack('H*', md5($this->_randomState));
				}
				$output = substr($output, 0, $count);
			}

			return $output;
		}

		private function encode64($input, $count)
		{
			$output = '';
			$i = 0;
			do {
				$value = ord($input[$i++]);
				$output .= $this->_itoa64[$value & 0x3f];
				if ($i < $count)
					$value |= ord($input[$i]) << 8;
				$output .= $this->_itoa64[($value >> 6) & 0x3f];
				if ($i++ >= $count)
					break;
				if ($i < $count)
					$value |= ord($input[$i]) << 16;
				$output .= $this->_itoa64[($value >> 12) & 0x3f];
				if ($i++ >= $count)
					break;
				$output .= $this->_itoa64[($value >> 18) & 0x3f];
			} while ($i < $count);

			return $output;
		}

		private function crypt($password, $setting)
		{
			$output = '*0';
			if (substr($setting, 0, 2) == $output)
				$output = '*1';

			$id = substr($setting, 0, 3);
			# We use "$P$", phpBB3 uses "$H$" for the same thing
			if ($id != '$P$' && $id != '$H$')
				return $output;

			$count_log2 = strpos($this->_itoa64, $setting[3]);
			if ($count_log2 < 7 || $count_log2 > 30)
				return $output;

			$count = 1 << $count_log2;

			$salt = substr($setting, 4, 8);
			if (strlen($salt) != 8)
				return $output;

			# We're kind of forced to use MD5 here since it's the only
			# cryptographic primitive available in all versions of PHP
			# currently in use.  To implement our own low-level crypto
			# in PHP would result in much worse performance and
			# consequently in lower iteration counts and hashes that are
			# quicker to crack (by non-PHP code).
			$hash = md5($salt . $password, TRUE);
			do {
				$hash = md5($hash . $password, TRUE);
			} while (--$count);

			$output = substr($setting, 0, 12);
			$output .= $this->encode64($hash, 16);

			return $output;
		}

		private function gensalt($input)
		{
			$output = '$P$';
			$output .= $this->_itoa64[min(($this->iterationCountLog2 + 5), 30)];
			$output .= $this->encode64($input, 6);

			return $output;
		}

		private function gensaltExtended($input)
		{
			$count_log2 = min($this->iterationCountLog2 + 8, 24);
			# This should be odd to not reveal weak DES keys, and the
			# maximum valid value is (2**24 - 1) which is odd anyway.
			$count = (1 << $count_log2) - 1;

			$output = '_';
			$output .= $this->_itoa64[$count & 0x3f];
			$output .= $this->_itoa64[($count >> 6) & 0x3f];
			$output .= $this->_itoa64[($count >> 12) & 0x3f];
			$output .= $this->_itoa64[($count >> 18) & 0x3f];

			$output .= $this->encode64($input, 3);

			return $output;
		}

		private function gensaltBlowfish($input)
		{
			# This one needs to use a different order of characters and a
			# different encoding scheme from the one in encode64() above.
			# We care because the last character in our encoded string will
			# only represent 2 bits.  While two known implementations of
			# bcrypt will happily accept and correct a salt string which
			# has the 4 unused bits set to non-zero, we do not want to take
			# chances and we also do not want to waste an additional byte
			# of entropy.
			$itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

			$output = '$2a$';
			$output .= chr(ord('0') + $this->iterationCountLog2 / 10);
			$output .= chr(ord('0') + $this->iterationCountLog2 % 10);
			$output .= '$';

			$i = 0;
			do {
				$c1 = ord($input[$i++]);
				$output .= $itoa64[$c1 >> 2];
				$c1 = ($c1 & 0x03) << 4;
				if ($i >= 16) {
					$output .= $itoa64[$c1];
					break;
				}

				$c2 = ord($input[$i++]);
				$c1 |= $c2 >> 4;
				$output .= $itoa64[$c1];
				$c1 = ($c2 & 0x0f) << 2;

				$c2 = ord($input[$i++]);
				$c1 |= $c2 >> 6;
				$output .= $itoa64[$c1];
				$output .= $itoa64[$c2 & 0x3f];
			} while (1);

			return $output;
		}

		protected function hashPassword($password)
		{
			$random = '';

			if (CRYPT_BLOWFISH == 1 && !$this->portableHashes) {
				$random = $this->getRandomBytes(16);
				$hash =
				    crypt($password, $this->gensaltBlowfish($random));
				if (strlen($hash) == 60)
					return $hash;
			}

			if (CRYPT_EXT_DES == 1 && !$this->portableHashes) {
				if (strlen($random) < 3)
					$random = $this->getRandomBytes(3);
				$hash =
				    crypt($password, $this->gensaltExtended($random));
				if (strlen($hash) == 20)
					return $hash;
			}

			if (strlen($random) < 6)
				$random = $this->getRandomBytes(6);
			$hash =
			    $this->crypt($password,
			    $this->gensalt($random));
			if (strlen($hash) == 34)
				return $hash;

			# Returning '*' on error is safe here, but would _not_ be safe
			# in a crypt(3)-like function used _both_ for generating new
			# hashes and for validating passwords against existing hashes.
			return '*';
		}

		protected function checkPassword($password, $stored_hash)
		{
			$hash = $this->crypt($password, $stored_hash);
			if ($hash[0] == '*')
				$hash = crypt($password, $stored_hash);

			return $hash == $stored_hash;
		}
	}