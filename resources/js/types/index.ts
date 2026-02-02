export type * from './auth';
export type * from './navigation';
export type * from './ui';
export type * from './models';
export { ACCOUNT_TYPES, TRANSACTION_TYPES, CATEGORY_TYPES, PAYMENT_METHOD_TYPES } from './models';

import type { Auth } from './auth';
import type { Currency } from './models';

export type SharedData = {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    currencies?: Currency[];
    [key: string]: unknown;
};
