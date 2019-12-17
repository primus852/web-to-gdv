<?php
/**
 * Created by PhpStorm.
 * User: torsten
 * Date: 27.08.2018
 * Time: 12:09
 */

namespace App\Util\Sftp;

use App\Util\Gdv\GdvException;
use phpseclib\Net\SFTP as Connection;
use primus852\SimpleCrypt\SimpleCrypt;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class Sftp
{


    private $server;
    private $username;
    private $password;
    private $folder;
    private $local;

    /* @var $sftp Connection */
    private $sftp;

    /**
     * Sftp constructor.
     * @param string|null $folder
     * @param string|null $server
     * @param string|null $username
     * @param string|null $enc_password
     * @param bool $local
     * @throws SftpException
     */
    public function __construct(string $folder = null, string $server = null, string $username = null, string $enc_password = null, bool $local = false)
    {

        $this->server = $server !== null ? $server : getenv('SFTP_HOST');
        $this->username = $username !== null ? $username : getenv('SFTP_USER');
        $this->password = $enc_password !== null ? SimpleCrypt::dec($enc_password, 'brasa', 'tw') : getenv('SFTP_PASS');
        $this->folder = $folder !== null ? $folder : getenv('SFTP_OUTBOX');
        $this->local = $local;

        if (!$this->local) {
            $this->login();
        }

    }

    /**
     * @throws SftpException
     */
    private function login()
    {

        if ($this->server === '' || $this->server === null) {
            throw new SftpException('Invalid Server: ' . $this->server);
        }

        if ($this->username === '' || $this->username === null) {
            throw new SftpException('Invalid Username: ' . $this->username);
        }

        if ($this->password === null) {
            throw new SftpException('Invalid Password: hidden');
        }

        /**
         * Create connection
         */
        try {
            $this->sftp = new Connection($this->server);
        } catch (\Exception $e) {
            throw new SftpException('Connection Error: ' . $e->getMessage());
        }

        /**
         * Actual Login
         */
        $login = $this->sftp->login($this->username, $this->password);

        if (!$login) {
            throw new SftpException('Login failed');
        }

        $this->sftp->chdir(getenv('SFTP_FOLDER'));
        $this->sftp->chdir($this->folder);

    }

    /**
     * @param string $filename
     * @param string $content
     * @param string $test
     * @throws GdvException
     */
    public function put_file(string $filename, string $content, string $test = 'false')
    {

        if (!$this->sftp->put('temp_' . $filename, $content, 2)) {
            throw new GdvException('Upload failed');
        }

        if ($test === 'false') {
            if (!$this->sftp->rename('temp_' . $filename, $filename)) {
                throw new GdvException('Renaming failed');
            }
        }

    }

    /**
     * @return array
     */
    public function files()
    {

        $files = array();


        if (!$this->local) {

            /**
             * Get Files from SFTP
             */
            $list = $this->sftp->nlist();
            if (!empty($list)) {
                foreach ($list as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $files[] = $file;
                    }
                }
            }
        } else {

            /**
             * Get all local Files
             */
            $finder = new Finder();
            $finder->files()->in(__DIR__.'/../../../public/debug');

            if ($finder->hasResults()) {
                foreach($finder as $file){
                    $files[] = $file;
                }
            }

        }

        return $files;

    }

    /**
     * @param string $file
     * @return bool
     */
    public function delete(string $file)
    {
        return $this->local ? null : $this->sftp->delete($file);
    }

    /**
     * @param string|SplFileInfo $file
     * @return mixed
     */
    public function content($file)
    {
        return $this->local ? file_get_contents($file->getRealPath()) : $this->sftp->get($file);
    }

    public function __destruct()
    {
        $this->sftp = null;
    }


    /**
     * @return mixed
     */
    public function getSftp()
    {
        return $this->sftp;
    }


}