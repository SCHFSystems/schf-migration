import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { migrationApi, MigrationProject, ValidationResult, PreviewData, MigrationPreview } from '../services/migrationApi';

export function useMigrationProjects(params?: Record<string, any>) {
  return useQuery({
    queryKey: ['migration-projects', params],
    queryFn: () => migrationApi.projects.list(params),
  });
}

export function useMigrationProject(id: number) {
  return useQuery({
    queryKey: ['migration-project', id],
    queryFn: () => migrationApi.projects.get(id),
    enabled: !!id,
  });
}

export function useCreateMigrationProject() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: Partial<MigrationProject>) =>
      migrationApi.projects.create(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['migration-projects'] });
    },
  });
}

export function useUpdateMigrationProject() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<MigrationProject> }) =>
      migrationApi.projects.update(id, data),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['migration-projects'] });
      queryClient.invalidateQueries({ queryKey: ['migration-project', id] });
    },
  });
}

export function useDeleteMigrationProject() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => migrationApi.projects.delete(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['migration-projects'] });
    },
  });
}

export function usePrepareMigration(projectId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => migrationApi.workflow.prepare(projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['migration-project', projectId] });
    },
  });
}

export function useValidateMigration(projectId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => migrationApi.workflow.validate(projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['migration-project', projectId] });
    },
  });
}

export function usePreviewMigration(projectId: number, tableName?: string) {
  return useQuery({
    queryKey: ['migration-preview', projectId, tableName],
    queryFn: () => migrationApi.workflow.preview(projectId, tableName),
    enabled: !!projectId,
  });
}

export function useMigrateMigration(projectId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => migrationApi.workflow.migrate(projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['migration-project', projectId] });
      queryClient.invalidateQueries({ queryKey: ['migration-preview', projectId] });
    },
  });
}

export function useBundlePreview(projectId: number) {
  return useQuery({
    queryKey: ['migration-bundle-preview', projectId],
    queryFn: () => migrationApi.bundle.preview(projectId),
    enabled: !!projectId,
  });
}

export function useExportBundle(projectId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => migrationApi.bundle.export(projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['migration-project', projectId] });
      queryClient.invalidateQueries({ queryKey: ['migration-bundle-preview', projectId] });
    },
  });
}

export function useRollbackMigration(projectId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => migrationApi.workflow.rollback(projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['migration-project', projectId] });
    },
  });
}

export function useMigrationReport(projectId: number) {
  return useQuery({
    queryKey: ['migration-report', projectId],
    queryFn: () => migrationApi.workflow.report(projectId),
    enabled: !!projectId,
  });
}

export function useAiConfigs(projectId: number) {
  return useQuery({
    queryKey: ['ai-configs', projectId],
    queryFn: () => migrationApi.aiConfig.list(projectId),
    enabled: !!projectId,
  });
}

export function useCreateAiConfig(projectId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: any) => migrationApi.aiConfig.create(projectId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ai-configs', projectId] });
    },
  });
}

export function useAnalyzeWithAi(projectId: number) {
  return useMutation({
    mutationFn: (data: { table_name: string; sample_data: Record<string, any>[] }) =>
      migrationApi.aiConfig.analyze(projectId, data),
  });
}

export function useTestConnection(projectId: number) {
  return useMutation({
    mutationFn: () => migrationApi.preview.testConnection(projectId),
  });
}

export function useGenerateInventory(projectId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => migrationApi.inventory.generate(projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['migration-project', projectId] });
    },
  });
}

export function useGeneratePreview(projectId: number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => migrationApi.previewGenerate.generate(projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['migration-project', projectId] });
      queryClient.invalidateQueries({ queryKey: ['migration-preview-result', projectId] });
    },
  });
}

export function usePreviewResult(projectId: number) {
  return useQuery({
    queryKey: ['migration-preview-result', projectId],
    queryFn: () => migrationApi.previewGenerate.getResult(projectId),
    enabled: !!projectId,
  });
}
