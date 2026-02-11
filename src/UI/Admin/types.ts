export interface DashboardData {
  overview: {
    activeProjects: number;
    pendingQuotes: number;
    utilizationRate: number;
    revenueThisMonth: number;
  };
  recentActivity: Array<{
    id: number;
    type: string;
    message: string;
    time: string;
  }>;
  skillHeatmap: Array<{
    skill_name: string;
    avg_rating: number;
  }>;
  kpiPerformance: Array<{
    kpi_name: string;
    avg_score: number;
  }>;
}

export interface Project {
  id: number;
  name: string;
  customerId: number;
  soldHours: number;
  soldValue: number;
  state: string;
  startDate?: string;
  endDate?: string;
  malleableData?: Record<string, any>;
  tasks: Task[];
  archivedAt: string | null;
}

export interface Task {
  id: number;
  name: string;
  estimatedHours: number;
  completed: boolean;
}

export interface Quote {
  id: number;
  customerId: number;
  state: string;
  version: number;
  totalValue: number;
  currency: string;
  acceptedAt?: string;
  lines: QuoteLine[];
  malleableData?: Record<string, any>;
}

export interface QuoteLine {
  id: number;
  description: string;
  quantity: number;
  unitPrice: number;
  total: number;
  group: string;
}

export interface TimeEntry {
  id: number;
  employeeId: number;
  taskId: number;
  start: string;
  end: string;
  duration: number;
  description: string;
  billable: boolean;
  status: string;
  malleableData?: Record<string, any>;
  createdAt?: string;
  archivedAt?: string | null;
}

export interface Customer {
  id: number;
  name: string;
  legalName?: string;
  contactEmail: string;
  status: string;
  malleableData?: Record<string, any>;
  createdAt: string;
  archivedAt: string | null;
}

export interface Site {
  id: number;
  customerId: number;
  name: string;
  addressLines: string | null;
  city: string | null;
  state: string | null;
  postalCode: string | null;
  country: string | null;
  status: string;
  malleableData?: Record<string, any>;
  createdAt?: string;
  archivedAt: string | null;
}

export interface Employee {
  id: number;
  wpUserId: number;
  firstName: string;
  lastName: string;
  email: string;
  status?: string;
  hireDate?: string;
  managerId?: number;
  teamIds?: number[];
  malleableData?: Record<string, any>;
  createdAt: string;
  archivedAt: string | null;
}

export interface Ticket {
  id: number;
  customerId: number;
  siteId?: number;
  slaId?: number;
  subject: string;
  description: string;
  status: string;
  priority: string;
  malleableData?: Record<string, any>;
  createdAt: string;
  updatedAt?: string | null;
  openedAt?: string | null;
  resolvedAt: string | null;
  closedAt?: string | null;
}

export interface Article {
  id: number;
  title: string;
  content: string;
  category: string;
  status: string;
  createdAt: string;
  updatedAt: string | null;
}

export interface ActivityLog {
  id: number;
  type: string;
  description: string;
  userId: number | null;
  relatedEntityType: string | null;
  relatedEntityId: number | null;
  createdAt: string;
}

export interface Setting {
  key: string;
  value: string;
  type: string;
  description: string;
  updatedAt: string | null;
}

export interface PetSettings {
  apiUrl: string;
  nonce: string;
  currentPage: string;
}

export interface Lead {
  id: number;
  customerId: number;
  subject: string;
  description: string;
  status: string;
  source?: string;
  estimatedValue?: number;
  malleableData?: Record<string, any>;
  createdAt: string;
  updatedAt?: string | null;
  convertedAt?: string | null;
}

export interface SchemaDefinition {
  id: number;
  entityType: string;
  version: number;
  schema: any;
  status: string;
}

export interface Sla {
  id: number;
  name: string;
  target_response_hours: number;
  target_resolution_hours: number;
}

export interface Team {
  id: number;
  name: string;
  description: string;
  leadId: number | null;
  members: number[];
}

export interface Skill {
  id: number;
  capability_id: number;
  name: string;
  description: string;
  status: string;
}

export interface Role {
  id: number;
  name: string;
  level: string;
  description: string;
  success_criteria: string;
  status: string;
  version: number;
  required_skills?: Record<number, { min_proficiency_level: number; importance_weight: number }>;
}

export interface Assignment {
  id: number;
  employee_id: number;
  role_id: number;
  start_date: string;
  end_date: string | null;
  allocation_pct: number;
  status: string;
}

export interface Certification {
  id: number;
  name: string;
  issuing_body: string;
  expiry_months: number;
  status: string;
}

export interface PersonCertification {
  id: number;
  employee_id: number;
  certification_id: number;
  obtained_date: string;
  expiry_date: string | null;
  evidence_url: string | null;
  status: string;
  certification_name?: string;
  issuing_body?: string;
}

export interface KpiDefinition {
  id: number;
  name: string;
  description: string;
  default_frequency: string;
  unit: string;
  created_at: string;
}

export interface RoleKpi {
  id: number;
  role_id: number;
  kpi_definition_id: number;
  weight_percentage: number;
  target_value: number;
  measurement_frequency: string;
  created_at: string;
  kpi_name?: string; // Enriched on frontend
  kpi_unit?: string; // Enriched on frontend
}

export interface PersonKpi {
  id: number;
  employee_id: number;
  kpi_definition_id: number;
  role_id: number;
  period_start: string;
  period_end: string;
  target_value: number;
  actual_value: number | null;
  score: number | null;
  status: string;
  created_at: string;
  kpi_name?: string; // Enriched on frontend
  kpi_unit?: string; // Enriched on frontend
}

declare global {
  interface Window {
    petSettings: PetSettings;
  }
}
