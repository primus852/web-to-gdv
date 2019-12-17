<?php

namespace App\Command;

use App\Util\Gdv\Gdv;
use App\Util\Gdv\GdvException;
use App\Util\Sftp\Sftp;
use App\Util\Sftp\SftpException;
use Doctrine\ORM\EntityManagerInterface as ObjectManager;
use Swift_Mailer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;

class SftpInboxCommand extends Command
{
    protected static $defaultName = 'sftp:inbox';

    private $em;
    private $mailer;
    private $twig;

    /**
     * SftpInboxCommand constructor.
     * @param ObjectManager $em
     * @param Swift_Mailer $mailer
     * @param Environment $twig
     * @param string|null $name
     */
    public function __construct(ObjectManager $em, Swift_Mailer $mailer, Environment $twig, ?string $name = null)
    {
        parent::__construct($name);

        $this->em = $em;
        $this->mailer = $mailer;
        $this->twig = $twig;
    }


    protected function configure()
    {
        $this
            ->setDescription('Check SFTP Folder for new cases')
            ->addArgument('local', InputArgument::OPTIONAL, 'Use local XML for debugging');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $local = $input->getArgument('local') === 'y' ? true : false;

        $io = new SymfonyStyle($input, $output);

        try {
            $sftp = new Sftp(null, null, null, null, $local);
        } catch (SftpException $e) {
            $io->error($e);
            exit();
        }

        $files = $sftp->files();

        if (empty($files)) {
            $io->warning('No new files found');
            exit();
        }

        foreach ($files as $file) {

            $prefix = strpos($file, "temp_");
            if ($prefix === false) {

                $io->note('Working on ' . $file . ':');

                $content = $sftp->content($file);
                try {
                    $gdv = new Gdv($content, $this->em, $this->mailer, $this->twig);
                } catch (GdvException $exception) {
                    $io->error('Could not create GDV: ' . $exception->getMessage());
                    exit();
                }

                if ($gdv) {
                    $io->success('-->XML parsed');

                    /**
                     * Remove File from 3C Server
                     */
                    $delete = $sftp->delete($file);
                    if ($delete) {
                        $io->success('-->Deleted File from 3C Server');
                    } else {
                        if($delete === null){
                            $io->warning('-->File not deleted (local debug)');
                        }else{
                            $io->error('-->Could not delete File from 3C Server');
                        }

                    }
                } else {
                    $io->error('-->Could not detect Job from XML File');
                }

            }

        }

    }
}
