<?php

namespace App\Command;

use App\Entity\User;
use App\Util\Gdv\Gdv;
use App\Util\Gdv\GdvException;
use App\Util\Sftp\Sftp;
use App\Util\Sftp\SftpException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SftpInboxCommand extends Command
{
    protected static $defaultName = 'sftp:inbox';

    private $em;
    private $mailer;
    private $twig;

    /**
     * SftpInboxCommand constructor.
     * @param EntityManagerInterface $em
     * @param \Swift_Mailer $mailer
     * @param \Twig_Environment $twig
     * @param null|string $name
     */
    public function __construct(EntityManagerInterface $em, \Swift_Mailer $mailer, \Twig_Environment $twig, ?string $name = null)
    {
        parent::__construct($name);

        $this->em = $em;
        $this->mailer = $mailer;
        $this->twig = $twig;
    }


    protected function configure()
    {
        $this
            ->setDescription('Check SFTP Folder for new cases');;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $io = new SymfonyStyle($input, $output);

        try {
            $sftp = new Sftp();
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
                try{
                    $gdv = new Gdv($content, $this->em, $this->mailer, $this->twig);
                }catch (GdvException $exception){
                    $io->error('Could not create GDV: '.$exception->getMessage());
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
                        $io->error('-->Could not delete File from 3C Server');
                    }
                } else {
                    $io->error('-->Could not detect Job from XML File');
                }

            }

        }

    }
}
