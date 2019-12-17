<?php

namespace App\Command;

use App\Entity\Action;
use App\Entity\Contract;
use App\Entity\Damage;
use App\Entity\MessageType;
use App\Util\Curl\Curl;
use Doctrine\ORM\EntityManagerInterface as ObjectManager;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GdvCrawlCommand extends Command
{
    protected static $defaultName = 'gdv:crawl';
    private $em;

    public function __construct(ObjectManager $em, ?string $name = null)
    {
        parent::__construct($name);

        $this->em = $em;
    }

    protected function configure()
    {
        $this
            ->setDescription('Crawl the GDV Pages');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $anlagen = array(
            116 => array(
                'length' => 3,
                'class' => MessageType::class
            ),
            115 => array(
                'length' => 3,
                'class' => Damage::class
            ),
            1 => array(
                'length' => 3,
                'class' => Contract::class
            ),
            119 => array(
                'length' => 2,
                'class' => Action::class
            ),
        );

        foreach ($anlagen as $key => $details) {

            $io->text('Crawling <fg=green>' . $key . '</>');

            try {
                new Curl($key, $details['length'], $details['class'], $this->em);
                $io->text('<fg=green>DONE</>');
                $io->newLine();
            } catch (Exception $e) {
                $io->error('Curl Error: ' . $e->getMessage());
                exit();
            }

        }

    }
}
