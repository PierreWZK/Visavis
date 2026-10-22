<?php

namespace App\Catalog;

/**
 * Cherche sur Wikimedia Commons une photo d'un modèle (« Renault Clio ») : la première vue extérieure dont le titre
 * nomme bien le modèle. Les photos de Commons sont libres, à créditer (auteur et licence).
 */
final class ImageFinder
{
    public const string USER_AGENT = 'Vis-a-vis/1.0 (https://visavis.projectp.fr; comparateur de voitures, projet personnel)';

    private const string API = 'https://commons.wikimedia.org/w/api.php';

    /** Ce qui n'est pas la voiture vue de l'extérieur */
    private const string EXCLUDED = '/\b(interior|interieur|innenraum|cockpit|dashboard|armaturenbrett|engine|motor|motorraum|logo|emblem|badge|heck|rear|wheel|felge|seats?|sitze?|kofferraum|trunk|boot|key|steering|lenkrad|detail|lights?|scheinwerfer|toy|lego|modell|crash|police|polizei|rally|rallye|race|racing|t16|classic|oldtimer|historic|and|und|with)\b/i';

    /**
     * @return array{url: string, page: string, credit: string}|null
     */
    public function find(string $brand, string $model): ?array
    {
        $query = self::query($brand, $model);
        $data = $this->get([
            'action' => 'query', 'format' => 'json', 'generator' => 'search', 'gsrnamespace' => 6, 'gsrlimit' => 30,
            'gsrsearch' => $query.' filetype:bitmap', 'prop' => 'imageinfo', 'iiprop' => 'url|size|extmetadata',
            'iiurlwidth' => 960, 'iiextmetadatafilter' => 'Artist|LicenseShortName',
        ]);
        $pages = $data['query']['pages'] ?? [];
        usort($pages, fn (array $a, array $b) => $a['index'] <=> $b['index']);

        // Chaque mot du modèle doit figurer dans le titre du fichier (« 3 » et « Series » pour la Série 3)
        $words = array_filter(explode(' ', self::fold(trim(substr($query, \strlen($brand))))));
        $found = [];
        foreach ($pages as $page) {
            $info = $page['imageinfo'][0] ?? null;
            $title = self::fold($page['title']);
            if (!$info || !isset($info['thumburl']) || preg_match(self::EXCLUDED, $title)
                || $info['width'] < 1200 || $info['width'] < $info['height'] * 1.2) {
                continue;
            }
            foreach ($words as $word) {
                if (!preg_match('/(^|[^a-z0-9])'.preg_quote($word, '/').'([^a-z0-9]|$)/', $title)) {
                    continue 2;
                }
            }

            $meta = $info['extmetadata'] ?? [];
            $artist = trim(html_entity_decode(strip_tags($meta['Artist']['value'] ?? ''), \ENT_QUOTES)) ?: 'auteur inconnu';
            $found[] = [
                'url' => strtok($info['thumburl'], '?'),
                'page' => $info['descriptionurl'],
                'credit' => mb_substr($artist, 0, 120).', '.($meta['LicenseShortName']['value'] ?? 'licence libre'),
                'recent' => preg_match('/20(1[7-9]|2\d)/', $title),
            ];
        }

        if (!$found) {
            return null;
        }
        // Une photo récente (année 2017 ou plus dans le titre) d'abord : la génération vendue aujourd'hui
        usort($found, fn (array $a, array $b) => $b['recent'] <=> $a['recent']);
        unset($found[0]['recent']);

        return $found[0];
    }

    /** La même photo dans une autre largeur de vignette (20, 40, 60, 120, 250, 330, 500 ou 960 px, celles que Commons sert) */
    public static function resize(string $url, int $width): string
    {
        return preg_replace('#/\d+px-([^/]+)$#', '/'.$width.'px-$1', $url);
    }

    /** Le nom tel que Commons l'écrit : « BMW 3 Series » pour « BMW Série 3 » */
    public static function query(string $brand, string $model): string
    {
        $model = preg_replace('/^Série (\w+)$/u', '$1 Series', $model);

        return $brand.' '.$model;
    }

    private static function fold(string $text): string
    {
        $text = mb_strtolower(str_replace('_', ' ', $text));

        return strtr($text, ['é' => 'e', 'è' => 'e', 'ë' => 'e', 'ö' => 'o', 'ü' => 'u', 'ä' => 'a', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ô' => 'o', 'ç' => 'c']);
    }

    private function get(array $params): array
    {
        $context = stream_context_create(['http' => ['timeout' => 20, 'header' => 'User-Agent: '.self::USER_AGENT."\r\n"]]);
        $body = @file_get_contents(self::API.'?'.http_build_query($params), false, $context);

        return false === $body ? [] : (json_decode($body, true) ?? []);
    }
}
