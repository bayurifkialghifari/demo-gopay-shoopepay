const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

export class ApiError extends Error {
  constructor(message, status, payload) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

export async function apiRequest(action, data = {}, signal) {
  const response = await fetch('api.php', {
    method: 'POST',
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
    },
    body: JSON.stringify({ action, ...data }),
    signal,
  });

  let payload;
  try {
    payload = await response.json();
  } catch {
    throw new ApiError('Server mengirim respons yang tidak valid.', response.status, null);
  }

  if (!response.ok || payload.success === false) {
    throw new ApiError(payload.message || 'Request tidak dapat diproses.', response.status, payload);
  }

  return payload;
}
