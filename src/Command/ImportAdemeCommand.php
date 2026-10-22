<?php

namespace App\Command;

use App\Catalog\AdemeImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:import:ademe', description: 'Importe les voitures neuves du jeu « ADEME - Car Labelling » dans le catalogue')]
final class ImportAdemeCommand extends Command
{
    public function __construct(private readonly AdemeImporter $importer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = $this->importer->fetch();
        $io->writeln(\count($rows).' lignes reçues de l\'ADEME');
        $result = $this->importer->import($rows);
        $io->success(\sprintf('%d versions créées, %d mises à jour, %d retirées, %d lignes ignorées.', ...array_values($result)));

        $io->writeln('Photos des nouveaux modèles (Wikimedia Commons)…');
        $images = $this->importer->findImages();
        $io->success(\sprintf('%d photos trouvées, %d modèles sans photo.', $images['found'], $images['missing']));

        return Command::SUCCESS;
    }
}
