import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import ReportTableCard from '@/components/reports/ReportTableCard';
import ReportToolbar from '@/components/reports/ReportToolbar';
import { usePagination } from '@/hooks/usePagination';
import { useReportFilters } from '@/hooks/useReportFilters';
import { exportCSV } from '@/lib/export';
import { formatDateFr } from '@/lib/format';
import OperationDetailsModal from '@/features/dashboard/OperationDetailsModal';

interface HarvestItem {
  id: string;
  date: string;
  num_workers: number;
  days_worked: number;
  quantity_harvested: number;
  daily_rate_at_entry: number;
}
interface PlotGroup { plot_id: string; plot_name: string; harvests: HarvestItem[] }
interface ApiData { plots: PlotGroup[] }

interface FlatRow {
  id: string;
  plot_id: string;
  plot_name: string;
  operation_date: string;
  num_workers: number;
  days_worked: number;
  quantity_harvested: number;
}

const HarvestingReport = () => {
  const { t } = useTranslation();
  const filters = useReportFilters();
  const [search, setSearch] = useState('');
  const [editId, setEditId] = useState<string | null>(null);

  const reportQuery = useQuery<ApiData>({
    queryKey: ['report-harvest', filters.apiParams],
    queryFn: async () => {
      const { data } = await api.get<{ data: ApiData }>('/reports/harvest', { params: filters.apiParams });
      return (data as { data?: ApiData }).data ?? { plots: [] };
    },
  });

  const flat = useMemo<FlatRow[]>(() => {
    const groups = reportQuery.data?.plots ?? [];
    const out: FlatRow[] = [];
    groups.forEach((g) => g.harvests.forEach((h) => out.push({
      id: h.id,
      plot_id: g.plot_id,
      plot_name: g.plot_name,
      operation_date: h.date,
      num_workers: h.num_workers,
      days_worked: h.days_worked,
      quantity_harvested: h.quantity_harvested,
    })));
    return out;
  }, [reportQuery.data]);

  const filteredOps = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return flat;
    return flat.filter((op) =>
      `${op.operation_date} ${op.plot_name} ${op.num_workers} ${op.quantity_harvested}`
        .toLowerCase().includes(q),
    );
  }, [flat, search]);

  const pagination = usePagination({
    rows: filteredOps,
    resetKey: `${search}|${filters.resetKey}`,
  });

  const handleExport = () => exportCSV(
    filteredOps.map((r) => ({
      [t('table.date', 'Date')]: formatDateFr(r.operation_date),
      [t('table.plot', 'Plot')]: r.plot_name,
      [t('table.workers', 'Workers')]: r.num_workers,
      [t('table.harvested', 'Harvested')]: `${r.quantity_harvested} kg`,
    })),
    'harvest-report',
  );

  return (
    <div className="space-y-3 sm:space-y-4 animate-fade-in">
      <ReportToolbar filters={filters} onExport={handleExport} />

      {reportQuery.isError && (
        <div className="stat-card text-center text-sm text-[hsl(var(--accent-danger))]">
          {t('reports.loadFailed')}
        </div>
      )}

      <ReportTableCard
        title={t('reports.harvestLog', 'Harvest log')}
        search={search}
        onSearchChange={setSearch}
        filteredCount={filteredOps.length}
        totalCount={flat.length}
        pagination={pagination}
        minWidth={420}
      >
        <table className="data-table min-w-[420px]">
          <thead>
            <tr>
              <th>{t('table.date', 'Date')}</th>
              <th>{t('table.plot', 'Plot')}</th>
              <th>{t('table.workers', 'Workers')}</th>
              <th>{t('table.harvested', 'Harvested')}</th>
            </tr>
          </thead>
          <tbody>
            {pagination.pageRows.length === 0 ? (
              <tr><td colSpan={4} className="text-center py-8">{t('common.noData', 'No data')}</td></tr>
            ) : pagination.pageRows.map((row) => (
              <tr
                key={row.id}
                onClick={() => setEditId(row.id)}
                title={t('reports.clickEdit', 'Click to edit')}
                className="cursor-pointer"
              >
                <td className="font-medium text-foreground whitespace-nowrap">{formatDateFr(row.operation_date)}</td>
                <td>{row.plot_name}</td>
                <td>{row.num_workers}</td>
                <td className="font-semibold text-foreground">{row.quantity_harvested.toLocaleString()} kg</td>
              </tr>
            ))}
          </tbody>
        </table>
      </ReportTableCard>

      <OperationDetailsModal
        open={!!editId}
        type="harvest"
        id={editId}
        onClose={() => setEditId(null)}
      />
    </div>
  );
};

export default HarvestingReport;
