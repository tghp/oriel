import { execSync } from 'node:child_process';
import { BASE_URL } from './urls';

// A rendered fixture page proves the whole chain: nginx → php-fpm → WP
// installed → plugin active → fixture mu-plugin registering forms.
const PROBE_URL = `${BASE_URL}/kitchen-sink/`;

async function stackIsReady(): Promise<boolean> {
  try {
    const res = await fetch(PROBE_URL, { redirect: 'manual' });
    if (res.status !== 200) {
      return false;
    }
    const body = await res.text();
    return body.includes('oriel-form-kitchen_sink');
  } catch {
    return false;
  }
}

export default async function globalSetup(): Promise<void> {
  if (await stackIsReady()) {
    return;
  }

  console.log('Oriel E2E stack not reachable — running `npm run env:up`...');
  execSync('npm run env:up', { cwd: __dirname + '/..', stdio: 'inherit' });

  if (!(await stackIsReady())) {
    throw new Error(
      `Stack came up but ${PROBE_URL} did not render the kitchen_sink form. ` +
        'Check `docker compose -f docker/docker-compose.yml logs` and that the ' +
        'fixture mu-plugin is mounted.',
    );
  }
}
