import { MAILPIT_URL } from './urls';

export type MailpitMessage = {
  ID: string;
  Subject: string;
  To: Array<{ Address: string }>;
  Snippet: string;
};

export type MailpitMessageDetail = MailpitMessage & {
  Text: string;
  HTML: string;
};

/**
 * Poll Mailpit's search API until a message matching the query appears.
 * Query is Mailpit search syntax — a bare marker string works.
 */
export async function waitForMessage(
  query: string,
  { timeoutMs = 10_000, intervalMs = 250 } = {},
): Promise<MailpitMessageDetail> {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    const res = await fetch(
      `${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`,
    );

    if (res.ok) {
      const body = (await res.json()) as { messages: MailpitMessage[] };

      if (body.messages.length > 0) {
        const detail = await fetch(
          `${MAILPIT_URL}/api/v1/message/${body.messages[0].ID}`,
        );
        return (await detail.json()) as MailpitMessageDetail;
      }
    }

    await new Promise((r) => setTimeout(r, intervalMs));
  }

  throw new Error(`No Mailpit message matched "${query}" within ${timeoutMs}ms`);
}

/** Search without waiting; returns matches (possibly empty). */
export async function searchMessages(
  query: string,
): Promise<MailpitMessage[]> {
  const res = await fetch(
    `${MAILPIT_URL}/api/v1/search?query=${encodeURIComponent(query)}`,
  );

  if (!res.ok) {
    throw new Error(`Mailpit search failed: ${res.status}`);
  }

  const body = (await res.json()) as { messages: MailpitMessage[] };
  return body.messages;
}
