import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

export const CAMPAIGN_ALL = 'all';

interface CampaignLite {
  id: string;
  name: string;
  start_date: string;
  end_date: string;
  is_active?: boolean;
}

export interface ReportFiltersState {
  plotId: string;
  campaignId: string;
  dateFrom: string;
  dateTo: string;
  setPlotId: (id: string) => void;
  setCampaignId: (id: string) => void;
  setDateFrom: (d: string) => void;
  setDateTo: (d: string) => void;
  inRange: (iso: string) => boolean;
  resetKey: string;
  range: { from: string; to: string };
  /** Params object suitable for the /reports/* endpoints. */
  apiParams: Record<string, string | string[]>;
}

interface Options {
  initialPlotId?: string;
  /**
   * When true (default), `campaignId` defaults to the campaign whose
   * [start_date, end_date] interval contains today's date. Falls back to
   * `is_active`, then the first available campaign.
   */
  defaultActiveCampaign?: boolean;
}

/** Pick the campaign whose interval contains today (YYYY-MM-DD compare). */
function pickCurrentCampaign<T extends { start_date: string; end_date: string; is_active?: boolean }>(
  list: T[],
): T | undefined {
  const today = new Date().toISOString().slice(0, 10);
  return (
    list.find((c) => c.start_date <= today && today <= c.end_date) ??
    list.find((c) => c.is_active) ??
    list[0]
  );
}

export function useReportFilters({
  initialPlotId = 'all',
  defaultActiveCampaign = true,
}: Options = {}): ReportFiltersState {
  const [plotId, setPlotId] = useState(initialPlotId);
  const [campaignId, setCampaignId] = useState<string>(CAMPAIGN_ALL);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [campaignTouched, setCampaignTouched] = useState(false);

  const campaignsQuery = useQuery({
    queryKey: ['report-filter-campaigns'],
    queryFn: async () => {
      const { data } = await api.get<{ data: CampaignLite[] }>('/campaigns', {
        params: { per_page: 100 },
      });
      // Endpoint returns { data: [...] } (paginated) — unwrap.
      const payload = (data as unknown as { data?: CampaignLite[] }).data;
      return Array.isArray(payload) ? payload : [];
    },
    staleTime: 5 * 60 * 1000,
  });

  // Auto-select the campaign whose interval contains today, unless the user has changed it.
  useMemo(() => {
    if (!defaultActiveCampaign || campaignTouched) return;
    if (campaignId !== CAMPAIGN_ALL) return;
    const current = pickCurrentCampaign(campaignsQuery.data ?? []);
    if (current) setCampaignId(current.id);
  }, [defaultActiveCampaign, campaignsQuery.data, campaignTouched, campaignId]);

  const setCampaignIdTouched = (id: string) => {
    setCampaignTouched(true);
    setCampaignId(id);
  };

  const range = useMemo(() => {
    if (campaignId === CAMPAIGN_ALL) return { from: dateFrom, to: dateTo };
    const c = campaignsQuery.data?.find((x) => x.id === campaignId);
    if (!c) return { from: dateFrom, to: dateTo };
    return { from: dateFrom || c.start_date, to: dateTo || c.end_date };
  }, [campaignId, dateFrom, dateTo, campaignsQuery.data]);

  const inRange = (iso: string) => {
    if (range.from && iso < range.from) return false;
    if (range.to && iso > range.to) return false;
    return true;
  };

  // IMPORTANT: send `plot_id` (singular). axios serializes `plot_ids: [x]`
  // as repeated `plot_ids=x` which PHP collapses to a string, breaking the
  // backend `array` validation rule. The singular alias is normalised back
  // to plot_ids[] by ReportRequest::prepareForValidation.
  //
  // v4: we now ALSO send `campaign_id` so the backend can scope rows by
  // campaign FK + date window (handles legacy ops without campaign_id).
  const apiParams = useMemo(() => {
    const p: Record<string, string | string[]> = {};
    if (plotId !== 'all') p.plot_id = plotId;
    if (campaignId && campaignId !== CAMPAIGN_ALL) p.campaign_id = campaignId;
    if (range.from) p.date_from = range.from;
    if (range.to) p.date_to = range.to;
    return p;
  }, [plotId, campaignId, range.from, range.to]);


  return {
    plotId,
    campaignId,
    dateFrom,
    dateTo,
    setPlotId,
    setCampaignId: setCampaignIdTouched,
    setDateFrom,
    setDateTo,
    inRange,
    range,
    apiParams,
    resetKey: `${plotId}|${campaignId}|${dateFrom}|${dateTo}`,
  };
}
