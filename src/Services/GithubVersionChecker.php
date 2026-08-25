<?php

declare(strict_types=1);

namespace GlpiPlugin\Grcmanager\Services;

/**
 * Dernière version publiée sur GitHub (release la plus récente, hors pre-release), pour affichage
 * à côté de la version installée sur l'écran Configuration - même mécanisme que les plugins
 * jumeaux glpi-vulnerability-manager, assetsign-glpi et Configuration-glpi-auto
 * (`GithubVersionChecker::getLatestGithubVersion()` / `Config::getLatestGithubVersion()` selon le
 * plugin), repris ici tel quel plutôt que réinventé.
 *
 * Mise en cache 24h (même durée/mécanisme que `RSSFeed::getRSSFeed()` du cœur GLPI, `$GLPI_CACHE`) :
 * l'API GitHub non authentifiée est limitée à 60 requêtes/heure par IP, largement insuffisant si
 * appelée à chaque affichage de la page. `Toolbox::getURLContent()` (pas un appel HTTP direct) :
 * réutilise la gestion de proxy/timeout/erreurs déjà établie par le cœur GLPI pour ce type d'appel,
 * même fonction que `Toolbox::checkNewVersionAvailable()`, qui fait exactement ceci pour GLPI
 * lui-même.
 */
final class GithubVersionChecker
{
    /**
     * @return string|null Numéro de version (sans le "v" du tag), ou null si l'appel a échoué
     *         (pas de connexion, API GitHub indisponible...).
     */
    public static function getLatestGithubVersion(): ?string
    {
        global $GLPI_CACHE;

        $cacheKey = 'plugin_grcmanager_latest_github_version';
        $cached = $GLPI_CACHE->get($cacheKey);
        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        $error = '';
        $url = 'https://api.github.com/repos/parime/glpi-grc-manager/releases/latest';
        $json = \Toolbox::getURLContent($url, $error);
        $version = null;
        if (!empty($json)) {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['tag_name']) && is_string($data['tag_name'])) {
                $version = ltrim($data['tag_name'], 'v');
            }
        }

        $GLPI_CACHE->set($cacheKey, $version ?? '', DAY_TIMESTAMP);

        return $version;
    }
}
