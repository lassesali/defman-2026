import type {
  AnalyzerQueueItem,
  AuthUser,
  DefectReport,
  Product,
} from '../types/defman';

const API_BASE = import.meta.env?.VITE_API_BASE_URL ?? 'http://localhost:8080';

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = localStorage.getItem('defman_token');
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(init.headers as Record<string, string> | undefined),
  };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers,
  });

  const data = await res.json();
  if (!res.ok) {
    const message =
      data.error ||
      (Array.isArray(data.errors) ? data.errors.join(' - ') : 'Request failed');
    throw new Error(message);
  }
  return data as T;
}

export const defmanApi = {
  // Auth
  login: (username: string, password: string) =>
    request<{ token: string; user: AuthUser }>('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    }),
  logout: () =>
    request<{ message: string }>('/api/auth/logout', { method: 'POST' }),
  applyForAccount: (payload: {
    username: string;
    password: string;
    verify_password: string;
    email: string;
    name?: string;
    company?: string;
    phone?: string;
  }) =>
    request<{ id: number; message: string }>('/api/auth/apply', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  lostPassword: (email: string) =>
    request<{ message: string; reset_token?: string }>(
      '/api/auth/lost-password',
      {
        method: 'POST',
        body: JSON.stringify({ email }),
      }
    ),

  // Products
  getProducts: () =>
    request<{ products: Product[] }>('/api/products'),
  createProduct: (name: string) =>
    request<{ id: number; name: string; message: string }>(
      '/api/admin/products',
      {
        method: 'POST',
        body: JSON.stringify({ name }),
      }
    ),
  deleteProduct: (id: number) =>
    request<{ message: string }>(`/api/admin/products/${id}`, {
      method: 'DELETE',
    }),

  // Defects (Public & Customer)
  submitPublicDefect: (payload: Record<string, unknown>) =>
    request<{ id: number; message: string }>('/api/defects/public', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  submitCustomerDefect: (payload: Record<string, unknown>) =>
    request<{ id: number; message: string }>('/api/defects', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  getDefects: (params: Record<string, string | number | undefined> = {}) => {
    const qs = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== '') qs.set(k, String(v));
    });
    return request<{ count: number; defects: DefectReport[] }>(
      `/api/defects?${qs.toString()}`
    );
  },
  getDefectById: (id: number) =>
    request<DefectReport>(`/api/defects/${id}`),
  updateDefectReport: (id: number, payload: Record<string, unknown>) =>
    request<{ message: string }>(`/api/defects/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),
  deleteDefect: (id: number) =>
    request<{ message: string }>(`/api/defects/${id}`, {
      method: 'DELETE',
    }),

  // Analyzer Queue, Triage & Resolution (Level 3)
  getAnalyzerQueue: (sort = 'all') =>
    request<{ count: number; queue: AnalyzerQueueItem[] }>(
      `/api/analyzer/queue?sort=${encodeURIComponent(sort)}`
    ),
  analyzeDefect: (id: number, payload: Record<string, unknown>) =>
    request<{ message: string }>(`/api/defects/${id}/analyze`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),
  resolveDefect: (id: number, payload: Record<string, unknown>) =>
    request<{ id: number; status: string; message: string }>(
      `/api/defects/${id}/resolve`,
      {
        method: 'PUT',
        body: JSON.stringify(payload),
      }
    ),

  // Admin Users & Profile
  getAdminUsers: () =>
    request<{
      users: AuthUser[];
      applicants: AuthUser[];
      generated_password_suggestion: string;
    }>('/api/admin/users'),
  createAdminUser: (payload: {
    username: string;
    password: string;
    email: string;
    userlevel: number;
  }) =>
    request<{ id: number; message: string }>('/api/admin/users', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),
  patchAdminUser: (
    id: number,
    payload: { action: 'approve' | 'role'; userlevel: number }
  ) =>
    request<{ message: string }>(`/api/admin/users/${id}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }),
  deleteAdminUser: (id: number) =>
    request<{ message: string }>(`/api/admin/users/${id}`, {
      method: 'DELETE',
    }),
  getProfile: () => request<AuthUser>('/api/users/me'),
  updateProfile: (payload: Record<string, unknown>) =>
    request<{ message: string }>('/api/users/me', {
      method: 'PUT',
      body: JSON.stringify(payload),
    }),
};
