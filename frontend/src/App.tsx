import { Link, Navigate, Route, Routes } from 'react-router-dom';
import MigrationDashboard from './pages/migration/MigrationDashboard';
import ProjectList from './pages/migration/ProjectList';
import ProjectCreate from './pages/migration/ProjectCreate';
import ProjectDetail from './pages/migration/ProjectDetail';
import SourceConfig from './pages/migration/SourceConfig';
import PreviewPage from './pages/migration/PreviewPage';
import PreviewData from './pages/migration/PreviewData';
import MigrationReport from './pages/migration/MigrationReport';
import AiConfig from './pages/migration/AiConfig';

export default function App() {
  return (
    <div>
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <Link to="/migration" className="text-lg font-bold text-slate-950">
            SCHF Migration
          </Link>
          <nav className="flex flex-wrap gap-3 text-sm font-medium text-slate-600">
            <Link className="hover:text-indigo-700" to="/migration">Dashboard</Link>
            <Link className="hover:text-indigo-700" to="/migration/projects">Projects</Link>
            <Link className="rounded-lg bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700" to="/migration/projects/new">
              Create Synthetic Project
            </Link>
          </nav>
        </div>
      </header>
      <Routes>
        <Route path="/" element={<Navigate to="/migration" replace />} />
        <Route path="/migration" element={<MigrationDashboard />} />
        <Route path="/migration/projects" element={<ProjectList />} />
        <Route path="/migration/projects/new" element={<ProjectCreate />} />
        <Route path="/migration/projects/:id" element={<ProjectDetail />} />
        <Route path="/migration/projects/:id/source" element={<SourceConfig />} />
        <Route path="/migration/projects/:id/preview" element={<PreviewPage />} />
        <Route path="/migration/projects/:id/data-preview" element={<PreviewData />} />
        <Route path="/migration/projects/:id/report" element={<MigrationReport />} />
        <Route path="/migration/projects/:id/ai-config" element={<AiConfig />} />
        <Route path="*" element={<Navigate to="/migration" replace />} />
      </Routes>
    </div>
  );
}
