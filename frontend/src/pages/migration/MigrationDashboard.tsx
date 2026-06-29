import React from 'react';
import { Link } from 'react-router-dom';
import { useMigrationProjects } from '../../hooks/useMigration';

const statusColors: Record<string, string> = {
  draft: 'bg-gray-100 text-gray-800',
  preparing: 'bg-blue-100 text-blue-800',
  validating: 'bg-yellow-100 text-yellow-800',
  previewing: 'bg-indigo-100 text-indigo-800',
  migrating: 'bg-purple-100 text-purple-800',
  completed: 'bg-green-100 text-green-800',
  failed: 'bg-red-100 text-red-800',
  rolled_back: 'bg-orange-100 text-orange-800',
};

const sourceTypeLabels: Record<string, string> = {
  synthetic: 'Synthetic',
  firebird: 'Firebird',
  mysql: 'MySQL',
  sql_server: 'SQL Server',
  postgres: 'PostgreSQL',
  oracle: 'Oracle',
  zip: 'ZIP Package',
};

export default function MigrationDashboard() {
  const { data, isLoading } = useMigrationProjects({ per_page: 10 });

  const projects = data?.data?.data || [];
  const stats = {
    total: data?.data?.total || 0,
    active: projects.filter(p => ['preparing', 'validating', 'previewing', 'migrating'].includes(p.status)).length,
    completed: projects.filter(p => p.status === 'completed').length,
    failed: projects.filter(p => p.status === 'failed').length,
  };

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <div className="max-w-7xl mx-auto">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">SCHF Migration</h1>
          <p className="mt-2 text-gray-600">
            Migrate data from legacy systems into SCHF Core
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <div className="bg-white rounded-lg shadow p-6">
            <div className="text-sm font-medium text-gray-500">Total Projects</div>
            <div className="mt-2 text-3xl font-semibold text-gray-900">{stats.total}</div>
          </div>
          <div className="bg-white rounded-lg shadow p-6">
            <div className="text-sm font-medium text-gray-500">Active</div>
            <div className="mt-2 text-3xl font-semibold text-blue-600">{stats.active}</div>
          </div>
          <div className="bg-white rounded-lg shadow p-6">
            <div className="text-sm font-medium text-gray-500">Completed</div>
            <div className="mt-2 text-3xl font-semibold text-green-600">{stats.completed}</div>
          </div>
          <div className="bg-white rounded-lg shadow p-6">
            <div className="text-sm font-medium text-gray-500">Failed</div>
            <div className="mt-2 text-3xl font-semibold text-red-600">{stats.failed}</div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow">
          <div className="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 className="text-lg font-semibold text-gray-900">Recent Projects</h2>
            <Link
              to="/migration/projects/new"
              className="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
              Create Synthetic Project
            </Link>
          </div>

          {isLoading ? (
            <div className="p-6 text-center text-gray-500">Loading...</div>
          ) : projects.length === 0 ? (
            <div className="p-6 text-center text-gray-500">
              No migration projects yet. Create one to get started.
            </div>
          ) : (
            <div className="divide-y divide-gray-200">
              {projects.map((project) => (
                <Link
                  key={project.id}
                  to={`/migration/projects/${project.id}`}
                  className="block px-6 py-4 hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <div className="flex items-center gap-3">
                        <h3 className="text-sm font-medium text-gray-900">{project.name}</h3>
                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${statusColors[project.status] || 'bg-gray-100 text-gray-800'}`}>
                          {project.status}
                        </span>
                      </div>
                      <p className="mt-1 text-sm text-gray-500">
                        {sourceTypeLabels[project.source_type] || project.source_type}
                        {project.description && ` - ${project.description}`}
                      </p>
                    </div>
                    <div className="text-right">
                      {project.progress !== null && (
                        <div className="text-sm font-medium text-gray-900">{project.progress}%</div>
                      )}
                      <div className="text-xs text-gray-500">
                        {new Date(project.created_at).toLocaleDateString()}
                      </div>
                    </div>
                  </div>
                  {project.progress !== null && (
                    <div className="mt-2">
                      <div className="w-full bg-gray-200 rounded-full h-2">
                        <div
                          className="bg-indigo-600 h-2 rounded-full transition-all"
                          style={{ width: `${project.progress}%` }}
                        />
                      </div>
                    </div>
                  )}
                </Link>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
