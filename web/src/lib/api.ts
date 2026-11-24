import axios, { AxiosError } from 'axios';

export const TOKEN_KEY = 'teamboard.token';

export const api = axios.create({
    baseURL: `${import.meta.env.VITE_API_URL ?? 'http://localhost:8002'}/api/v1`,
    headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY);
    if (token) config.headers.Authorization = `Bearer ${token}`;

    // Lets the API skip echoing our own changes back to us (broadcast()->toOthers()).
    const socketId = window.Echo?.socketId();
    if (socketId) config.headers['X-Socket-ID'] = socketId;

    return config;
});

// A revoked or expired token sends the user back to the login page.
api.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
        if (error.response?.status === 401 && !location.pathname.startsWith('/login')) {
            localStorage.removeItem(TOKEN_KEY);
            location.href = `/login?redirect=${encodeURIComponent(location.pathname)}`;
        }
        return Promise.reject(error);
    },
);

/** Laravel validation errors as { field: 'first message' }. */
export function validationErrors(error: unknown): Record<string, string> {
    const data = (error as AxiosError<{ errors?: Record<string, string[]>; message?: string }>)?.response?.data;
    if (data?.errors) {
        return Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, v[0]]));
    }
    return { general: data?.message ?? 'Something went wrong. Please try again.' };
}
