import { execFileSync } from 'node:child_process';
import path from 'node:path';

const COMPOSE_FILE = path.resolve(__dirname, '../docker/docker-compose.yml');

/**
 * Run a wp-cli command in the stack's run-only cli service.
 * Args are passed as an array — no shell quoting pitfalls.
 */
export function wpCli(args: string[]): string {
  return execFileSync(
    'docker',
    ['compose', '-f', COMPOSE_FILE, 'run', '--rm', '-T', 'cli', 'wp', ...args],
    { encoding: 'utf8' },
  ).trim();
}

/** Run a PHP snippet inside WordPress and return stdout. */
export function wpEval(code: string): string {
  return wpCli(['eval', code]);
}

/**
 * Find the newest oriel_submission whose meta key/value matches, and return
 * all its meta (values unwrapped from single-element arrays). Returns null if
 * no submission matches — which delete_after_processing tests assert on.
 */
export function findSubmissionMeta(
  metaKey: string,
  metaValue: string,
): Record<string, string> | null {
  const php = `
    $posts = get_posts([
      'post_type'   => 'oriel_submission',
      'post_status' => 'any',
      'numberposts' => 1,
      'orderby'     => 'ID',
      'order'       => 'DESC',
      'meta_key'    => ${JSON.stringify(metaKey)},
      'meta_value'  => ${JSON.stringify(metaValue)},
    ]);
    if (!$posts) { echo 'null'; return; }
    $meta = get_post_meta($posts[0]->ID);
    echo json_encode(array_map(fn ($v) => count($v) === 1 ? $v[0] : $v, $meta));
  `;

  const out = wpEval(php);
  return JSON.parse(out);
}
