<?PHP

namespace extensions;

use Knight\armor\Cipher as KCipher;

class Cipher extends KCipher
{
    const JWT = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';

    public function encryptJWT(?string $authorization) : string
    {
        if (null === $authorization) return null;

        $base = static::JWT . chr(46) . $authorization;
        $base_cipher = $this->encrypt($base);
        return $base . chr(46) . $base_cipher;
    }

    public function decryptJWT(?string $authorization) :? string
    {
        if (null === $authorization) return null;

        $base_position = strrpos($authorization, chr(46));
        $base = substr($authorization, 0, $base_position);
        $base_cipher = substr($authorization, $base_position);
        $base_cipher = $this->decrypt($base_cipher);
        if (md5($base_cipher) === md5($base)) return substr($base, 1 + strpos($base, chr(46)));

        return null;
    }
}
