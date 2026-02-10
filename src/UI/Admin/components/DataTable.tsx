import React from 'react';

export interface Column<T> {
  key: keyof T;
  header: string;
  render?: (value: T[keyof T], item: T) => React.ReactNode;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  loading?: boolean;
  emptyMessage?: string;
}

export function DataTable<T extends { id: string | number }>({ 
  columns, 
  data, 
  loading = false, 
  emptyMessage = 'No data found.' 
}: DataTableProps<T>) {
  
  if (loading) {
    return <div className="pet-data-table-loading">Loading...</div>;
  }

  if (data.length === 0) {
    return <div className="pet-data-table-empty">{emptyMessage}</div>;
  }

  return (
    <div className="pet-data-table-container" style={{ overflowX: 'auto' }}>
      <table className="wp-list-table widefat fixed striped" style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr>
            {columns.map((col) => (
              <th key={String(col.key)} scope="col" style={{ textAlign: 'left', padding: '8px' }}>
                {col.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.map((item) => (
            <tr key={item.id}>
              {columns.map((col) => (
                <td key={`${item.id}-${String(col.key)}`} style={{ padding: '8px' }}>
                  {col.render ? col.render(item[col.key], item) : renderValue(item[col.key])}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function renderValue(value: unknown): React.ReactNode {
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return value;
  }
  if (value === null || value === undefined) {
    return '-';
  }
  if (Array.isArray(value)) {
    return `[Array ${value.length}]`;
  }
  if (typeof value === 'object') {
    return '[Object]';
  }
  return String(value);
}
