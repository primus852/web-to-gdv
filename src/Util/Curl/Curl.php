<?php
/**
 * Created by PhpStorm.
 * User: torsten
 * Date: 28.08.2018
 * Time: 10:46
 */

namespace App\Util\Curl;

use Doctrine\ORM\EntityManagerInterface as ObjectManager;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;

class Curl
{

    const URL = 'http://www.gdv-online.de/snetz/';
    const RELEASE = '2013';

    private $client;
    private $em;

    /**
     * Curl constructor.
     * @param int $type_no
     * @param int $length
     * @param string $class_name
     * @param ObjectManager $em
     * @throws CurlException
     * @throws GuzzleException
     */
    public function __construct(int $type_no, int $length, string $class_name, ObjectManager $em)
    {

        $this->client = new Client();
        $this->em = $em;

        try {
            $this->gather($type_no, $length, $class_name);
        } catch (CurlException $e) {
            throw new CurlException('Error Parsing Damage: ' . $e->getMessage());
        }

    }

    /**
     * @param int $type_no
     * @param int $id_size
     * @param string $class_name
     * @throws CurlException
     * @throws GuzzleException
     */
    private function gather(int $type_no, int $id_size, string $class_name)
    {

        $response = $this->client->request('GET', self::URL . 'release' . self::RELEASE . '/anl' . $type_no . '.htm');

        if ($response->getStatusCode() !== 200) {
            throw new CurlException('Wrong StatusCode: ' . $response->getStatusCode() . ' URL: ' . self::URL . 'release' . self::RELEASE . '/anl' . $type_no . '.htm');
        }

        $crawler = new Crawler($response->getBody()->getContents());
        $rows = $crawler->filter('table > tr');

        foreach ($rows as $row) {

            $nc = new Crawler($row);
            $nc->filter('td');

            preg_match_all('/([0-9]+)([^0-9]+)/', $nc->text(), $matches);

            if (!empty($matches[1][0])) {

                preg_match_all('/([0-9]+)/', substr($nc->text(), 0, $id_size), $matchesInner);

                if (!empty($matchesInner[1][0])) {

                    $no = substr($nc->text(), 0, $id_size);
                    $text = substr($nc->text(), $id_size, -1);

                    $entity = $this->em->getRepository($class_name)->findOneBy(array(
                        'gdv' => $no,
                    ));

                    if ($entity !== null) {
                        continue;
                    }

                    $entity = new $class_name();
                    $entity->setGdv($no);
                    $entity->setText($text);

                    try {
                        $this->em->persist($entity);
                    } catch (Exception $e) {
                        die($e->getMessage());
                    }
                }
            }
        }

        try {
            $this->em->flush();
        } catch (Exception $e) {
            throw new CurlException('MySQL Error: ' . $e->getMessage());
        }

    }
}