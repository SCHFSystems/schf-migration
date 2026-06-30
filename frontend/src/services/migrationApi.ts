import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8001/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && localStorage.getItem('auth_token')) {
      localStorage.removeItem('auth_token');
    }
    return Promise.reject(error);
  }
);

export interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  first_page_url: string;
  from: number | null;
  last_page: number;
  last_page_url: string;
  links: Array<{ url: string | null; label: string; active: boolean }>;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
}

export interface MigrationProject {
  id: number;
  name: string;
  description: string | null;
  source_type: 'synthetic' | 'firebird' | 'mysql' | 'sql_server' | 'postgres' | 'oracle' | 'zip';
  source_config: Record<string, any>;
  status: 'draft' | 'preparing' | 'validating' | 'previewing' | 'migrating' | 'completed' | 'failed' | 'rolled_back';
  ai_config: Record<string, any> | null;
  api_key_id: number | null;
  data_cutoff_date: string | null;
  organization_id: number | null;
  created_by: number | null;
  started_at: string | null;
  completed_at: string | null;
  error_message: string | null;
  created_at: string;
  updated_at: string;
  progress: number | null;
  status_color: string;
  imports?: MigrationImport[];
  latest_report?: MigrationReport;
  api_keys?: MigrationApiKey[];
}

export interface MigrationImport {
  id: number;
  migration_project_id: number;
  batch_number: number;
  table_name: string;
  records_total: number;
  records_imported: number;
  records_skipped: number;
  records_failed: number;
  status: 'pending' | 'running' | 'completed' | 'failed' | 'cancelled';
  started_at: string | null;
  completed_at: string | null;
  error_log: any[] | null;
  checksum: string | null;
  duration: number | null;
  success_rate: number;
}

export interface MigrationRecord {
  id: number;
  import_id: number;
  source_table: string;
  source_id: string | null;
  target_table: string | null;
  target_id: string | null;
  action: 'create' | 'update' | 'skip' | 'error';
  old_values: Record<string, any> | null;
  new_values: Record<string, any> | null;
  validation_errors: any[] | null;
  ai_suggestions: any[] | null;
  status: 'pending' | 'imported' | 'skipped' | 'failed' | 'rolled_back';
}

export interface MigrationReport {
  id: number;
  migration_project_id: number;
  summary: Record<string, any>;
  totals: {
    total_records: number;
    success: number;
    skipped: number;
    failed: number;
    success_rate: number;
  };
  duration_seconds: number | null;
  core_version: string | null;
  migration_version: string | null;
  legacy_version: string | null;
  package_hash: string | null;
  operator_id: number | null;
  backup_path: string | null;
  status: 'draft' | 'final' | 'archived';
  created_at: string;
}

export interface MigrationApiKey {
  id: number;
  name: string;
  key: string;
  migration_project_id: number;
  permissions: string[] | null;
  last_used_at: string | null;
  expires_at: string | null;
  is_active: boolean;
  created_at: string;
}

export interface AiConfig {
  id: number;
  migration_project_id: number;
  provider: 'openai' | 'nvidia' | 'glm' | 'minimax' | 'kimi' | 'custom';
  api_key_encrypted: string;
  model: string | null;
  temperature: number;
  max_tokens: number;
  system_prompt: string | null;
  is_active: boolean;
  created_at: string;
  provider_label: string;
}

export interface ValidationSection {
  valid: boolean;
  issues: any[];
  warnings: any[];
  error_count: number;
  warning_count: number;
  quality_score?: number;
}

export interface ValidationResult {
  schema_validation: ValidationSection;
  referential_integrity: ValidationSection;
  duplicate_detection: ValidationSection;
  business_rules: ValidationSection;
  data_quality: ValidationSection;
  overall_valid: boolean;
  error_count: number;
  warning_count: number;
}

export interface PreviewData {
  original: Record<string, any>[];
  normalized: Record<string, any>[];
  column_mapping: Record<string, any>;
  total_records: number;
}

export interface BundlePreview {
  success: boolean;
  bundle_version: string;
  sdk_version: string;
  core_min_version: string;
  source: {
    type: string;
    tables: number;
    inventory_hash: string;
  };
  files: Array<{
    path: string;
    schema: string | null;
    required: boolean;
    records: number;
  }>;
  warnings: string[];
}

export interface BundleExportResult {
  success: boolean;
  bundle_path?: string;
  bundle_sha256?: string;
  download_url?: string;
  manifest?: Record<string, any>;
  error?: string;
}

export interface Inventory {
  generated_at: string;
  driver: string;
  tables: Array<{
    name: string;
    row_count: number;
    columns: Record<string, { type: string; nullable: boolean; default: any }>;
    column_names: string[];
    primary_keys: string[];
    foreign_keys: Record<string, string>;
    sample: Record<string, any>[];
  }>;
  summary: {
    total_tables: number;
    total_rows: number;
    total_columns: number;
  };
}

export interface NormalizationBundle {
  generated_at: string;
  summary: Record<string, number>;
  entities: Record<string, Record<string, any>[]>;
  issues: Array<{
    type: string;
    severity: 'error' | 'warning';
    entity: string;
    external_id: string;
    field: string;
    message: string;
    value: any;
  }>;
}

export interface QualityResult {
  ran_at: string;
  status: 'passed' | 'passed_with_warnings' | 'blocked';
  summary: {
    total_issues: number;
    total_errors: number;
    total_warnings: number;
  };
  issues: NormalizationBundle['issues'];
}

export interface MigrationPreview {
  project_id: number;
  status: 'ready' | 'blocked';
  ready_for_bundle: boolean;
  summary: {
    total_records: number;
    valid_records: number;
    warning_count: number;
    error_count: number;
    ignored_count: number;
    historical_count: number;
  };
  entities: Record<string, {
    total: number;
    valid: number;
    warnings: number;
    errors: number;
    ignored: number;
    historical: number;
  }>;
  warnings: Array<{
    type: string;
    entity: string;
    external_id: string;
    field: string;
    message: string;
    value: any;
  }>;
  errors: Array<{
    type: string;
    entity: string;
    external_id: string;
    field: string;
    message: string;
    value: any;
  }>;
  ignored: Array<{
    entity: string;
    external_id: string;
    reason: string;
  }>;
  historical: Array<{
    entity: string;
    external_id: string;
    reason: string;
  }>;
  generated_at: string;
}

export const migrationApi = {
  health: () => api.get<{ status: string; system: string }>('/health'),
  projects: {
    list: (params?: Record<string, any>) =>
      api.get<PaginatedResponse<MigrationProject>>('/projects', { params }),
    get: (id: number) =>
      api.get<MigrationProject>(`/projects/${id}`),
    create: (data: Partial<MigrationProject>) =>
      api.post<MigrationProject>('/projects', data),
    createSynthetic: (scenario: 'clean' | 'warnings' | 'blocked' = 'clean') =>
      api.post<MigrationProject>('/projects', {
        name: `Synthetic ${scenario} project`,
        description: 'Sprint 9 synthetic-only migration project',
        source_type: 'synthetic',
        source_config: {
          scenario,
          organization: {
            external_id: 'ORG-SYN',
            name: 'Synthetic Organization',
            legal_name: 'Synthetic Organization Ltd',
          },
        },
      }),
    update: (id: number, data: Partial<MigrationProject>) =>
      api.put<MigrationProject>(`/projects/${id}`, data),
    delete: (id: number) =>
      api.delete(`/projects/${id}`),
  },
  workflow: {
    prepare: (projectId: number) =>
      api.post(`/projects/${projectId}/prepare`),
    validate: (projectId: number) =>
      api.post(`/projects/${projectId}/validate`),
    preview: (projectId: number, tableName?: string) =>
      api.get(`/projects/${projectId}/preview`, { params: { table: tableName } }),
    migrate: (projectId: number) =>
      api.post(`/projects/${projectId}/migrate`),
    rollback: (projectId: number) =>
      api.post(`/projects/${projectId}/rollback`),
    report: (projectId: number) =>
      api.get(`/projects/${projectId}/report`),
  },
  aiConfig: {
    list: (projectId: number) =>
      api.get<AiConfig[]>(`/projects/${projectId}/ai-config`),
    create: (projectId: number, data: Partial<AiConfig>) =>
      api.post<AiConfig>(`/projects/${projectId}/ai-config`, data),
    update: (projectId: number, configId: number, data: Partial<AiConfig>) =>
      api.put<AiConfig>(`/projects/${projectId}/ai-config/${configId}`, data),
    delete: (projectId: number, configId: number) =>
      api.delete(`/projects/${projectId}/ai-config/${configId}`),
    analyze: (projectId: number, data: { table_name: string; sample_data: Record<string, any>[] }) =>
      api.post(`/projects/${projectId}/ai/analyze`, data),
  },
  preview: {
    list: (projectId: number, params?: { table?: string; limit?: number }) =>
      api.get<{ previews: Record<string, PreviewData> }>(`/projects/${projectId}/preview`, { params }),
    testConnection: (projectId: number) =>
      api.post<{ success: boolean; message: string }>(`/projects/${projectId}/preview/test-connection`),
  },
  reports: {
    list: (projectId: number) =>
      api.get<MigrationReport[]>(`/projects/${projectId}/reports`),
    get: (projectId: number, reportId: number) =>
      api.get<MigrationReport>(`/projects/${projectId}/reports/${reportId}`),
    latest: (projectId: number) =>
      api.get<MigrationReport>(`/projects/${projectId}/reports/latest`),
  },
  bundle: {
    preview: (projectId: number) =>
      api.get<BundlePreview>(`/projects/${projectId}/bundle/preview`),
    export: (projectId: number) =>
      api.post<BundleExportResult>(`/projects/${projectId}/bundle/export`),
    downloadUrl: (projectId: number) =>
      `${API_BASE_URL}/projects/${projectId}/bundle/download`,
  },
  inventory: {
    generate: (projectId: number) =>
      api.post<Inventory>(`/projects/${projectId}/inventory/generate`),
    get: (projectId: number) =>
      api.get<Inventory>(`/projects/${projectId}/inventory`),
  },
  normalization: {
    run: (projectId: number) =>
      api.post<NormalizationBundle>(`/projects/${projectId}/normalization/run`),
    get: (projectId: number) =>
      api.get<NormalizationBundle>(`/projects/${projectId}/normalization`),
  },
  quality: {
    run: (projectId: number) =>
      api.post<QualityResult>(`/projects/${projectId}/quality/run`),
    get: (projectId: number) =>
      api.get<QualityResult>(`/projects/${projectId}/quality`),
  },
  previewGenerate: {
    generate: (projectId: number) =>
      api.post<MigrationPreview>(`/projects/${projectId}/preview/generate`),
    getResult: (projectId: number) =>
      api.get<MigrationPreview>(`/projects/${projectId}/preview/result`),
  },
};

export default migrationApi;
