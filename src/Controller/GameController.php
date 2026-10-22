<?php

namespace App\Controller;

use App\Catalog\ImageFinder;
use App\Entity\Model;
use App\Entity\Version;
use App\Game\DailyGames;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Les jeux du jour. Les réponses restent sur le serveur : la page ne contient que ce qu'on a le droit de voir.
 */
final class GameController extends AbstractController
{
    public function __construct(private readonly DailyGames $games)
    {
    }

    #[Route('/jeu', name: 'app_game', methods: ['GET'])]
    public function index(): Response
    {
        $day = DailyGames::today();
        $blind = $this->games->blind($day);
        $mystery = $this->games->mystery($day);

        return $this->render('game/index.html.twig', [
            'date' => $day,
            'cars' => array_map(fn (Version $v) => [
                'label' => $v->getModel()->getBrand()->getName().' '.$v->getModel()->getName(),
                'version' => DailyGames::hidePower($v->getName()),
                'meta' => $v->getEnergy()->label().' · '.$v->getModel()->getBody()->label(),
                'model' => $v->getModel(),
            ], $blind['cars']),
            'mysteryHasImage' => $mystery->hasImage(),
            'questions' => array_map(fn (string $key) => DailyGames::QUESTIONS[$key][0], $blind['questions']),
            'firstClue' => DailyGames::clues($mystery, 0)[0],
            'tries' => DailyGames::TRIES,
            'models' => array_map(fn (Model $m) => ['id' => $m->getId(), 'label' => $m->getBrand()->getName().' '.$m->getName()], $this->games->models()),
        ]);
    }

    #[Route('/jeu/aveugle', name: 'app_game_blind', methods: ['POST'])]
    public function blind(Request $request): JsonResponse
    {
        $body = $this->body($request);
        $question = $body['question'] ?? null;
        $choice = $body['choice'] ?? null;
        if (!\in_array($question, [0, 1, 2], true) || !\in_array($choice, [0, 1, 2, 3], true)) {
            return $this->json(['message' => 'Réponse invalide.'], 422);
        }

        $blind = $this->games->blind(DailyGames::today());
        $key = $blind['questions'][$question];
        [, , $unit] = DailyGames::QUESTIONS[$key];
        $answer = DailyGames::winner($blind['cars'], $key);

        return $this->json([
            'correct' => $answer === $choice,
            'answer' => $answer,
            'values' => array_map(fn (Version $v) => number_format($v->{'get'.ucfirst($key)}(), 'consumption' === $key ? 1 : 0, ',', ' ').' '.$unit, $blind['cars']),
            // Une fois la partie jouée, on peut ouvrir la vraie comparaison
            'compare' => 2 === $question ? $this->generateUrl('app_compare', ['v' => implode(',', array_map(fn (Version $v) => $v->getId(), $blind['cars']))]) : null,
        ]);
    }

    #[Route('/jeu/devine', name: 'app_game_guess', methods: ['POST'])]
    public function guess(Request $request): JsonResponse
    {
        $body = $this->body($request);
        $wrong = $body['wrong'] ?? null;
        $models = [];
        foreach ($this->games->models() as $model) {
            $models[$model->getId()] = $model;
        }
        $guess = $models[$body['model'] ?? null] ?? null;
        if (!$guess || !\is_int($wrong) || $wrong < 0 || $wrong >= DailyGames::TRIES) {
            return $this->json(['message' => 'Choisissez un modèle dans la liste.'], 422);
        }

        $answer = $this->games->mystery(DailyGames::today());
        $correct = $guess === $answer;
        $over = $correct || $wrong + 1 >= DailyGames::TRIES;

        return $this->json([
            'correct' => $correct,
            'row' => DailyGames::compare($guess, $answer),
            'clues' => $correct ? [] : DailyGames::clues($answer, $wrong + 1),
            'answer' => $over ? [
                'label' => $answer->getBrand()->getName().' '.$answer->getName(),
                'url' => $this->generateUrl('app_catalog', ['q' => $answer->getBrand()->getName().' '.$answer->getName()]),
                'credit' => $answer->hasImage() ? ['page' => $answer->getImagePage(), 'text' => $answer->getImageCredit()] : null,
            ] : null,
        ]);
    }

    /**
     * La photo du modèle à deviner, plus ou moins floue. Servie par le site, pour que son nom de fichier ne dévoile rien.
     * Les niveaux 0 à 5 partent d'une vignette minuscule (le navigateur l'agrandit et la floute) : jamais nette avant la fin.
     */
    #[Route('/jeu/devine/photo/{level}', name: 'app_game_photo', requirements: ['level' => '[0-6]'], methods: ['GET'])]
    public function photo(int $level, #[Autowire('%kernel.share_dir%')] string $shareDir): Response
    {
        $day = DailyGames::today();
        $model = $this->games->mystery($day);
        if (!$model->hasImage()) {
            throw $this->createNotFoundException();
        }

        $file = $shareDir.'/jeu/'.$day->format('Y-m-d').'-'.$model->getId().'-'.$level.'.jpg';
        if (!is_file($file)) {
            $context = stream_context_create(['http' => ['timeout' => 15, 'header' => 'User-Agent: '.ImageFinder::USER_AGENT."
"]]);
            $bytes = @file_get_contents(ImageFinder::resize($model->getImageUrl(), DailyGames::PHOTO_WIDTHS[$level]), false, $context);
            if (false === $bytes) {
                throw $this->createNotFoundException();
            }
            @mkdir(\dirname($file), 0775, true);
            file_put_contents($file, $bytes);
        }

        $response = new BinaryFileResponse($file, 200, ['Content-Type' => 'image/jpeg']);
        $response->setPrivate()->setMaxAge(3600);

        return $response;
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
