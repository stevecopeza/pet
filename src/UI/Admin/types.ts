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
}

export interface Project {
  id: number;
  name: string;
  customerId: number;
  soldHours: number;
  tasks: Task[];
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
  lines: QuoteLine[];
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
}

export interface Customer {
  id: number;
  name: string;
  contactEmail: string;
  createdAt: string;
  archivedAt: string | null;
}

export interface Employee {
  id: number;
  wpUserId: number;
  firstName: string;
  lastName: string;
  email: string;
  createdAt: string;
  archivedAt: string | null;
}

export interface Ticket {
  id: number;
  customerId: number;
  subject: string;
  description: string;
  status: string;
  priority: string;
  createdAt: string;
  resolvedAt: string | null;
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

declare global {
  interface Window {
    petSettings: PetSettings;
  }
}
