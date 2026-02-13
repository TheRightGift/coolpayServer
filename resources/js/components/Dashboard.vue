<template>
  <div class="grey lighten-5">
    <header class="teal darken-2 z-depth-1">
      <div class="container">
        <div class="flex-header">
          <div>
            <h1 class="header light white-text" style="margin: 0; padding-top: 15px;">Dashboard</h1>
            <p class="white-text light" style="padding-bottom: 15px;">Welcome back, {{ user.name }}!</p>
          </div>
          <div>
            <a @click="logout" class="btn waves-effect waves-light red darken-1">
              Logout
            </a>
          </div>
        </div>
      </div>
    </header>

    <div class="container section">
      <div class="row">
        <div class="col s12 m4">
          <div class="card white hoverable">
            <div class="card-content">
              <div class="flex-stat-content">
                <div class="flex-items-center">
                  <div class="circle green lighten-4 green-text text-darken-3 stat-icon-box">
                    <i class="material-icons">account_balance_wallet</i>
                  </div>
                  <div class="ml-4">
                    <h3 class="grey-text text-darken-1 stat-title">Wallet Balance</h3>
                    <p class="teal-text text-darken-2 stat-value">₦{{ formatMoney(wallet.actual_balance || 0) }}</p>
                  </div>
                </div>
                <a @click="refreshBalance"
                  class="btn-flat waves-effect blue-text text-darken-1"
                  :class="{ 'animate-spin-m': refreshingBalance }"
                  :disabled="refreshingBalance"
                >
                  <i class="material-icons">refresh</i>
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="col s12 m4">
          <div class="card white hoverable">
            <div class="card-content">
              <div class="flex-stat-content">
                <div class="flex-items-center">
                  <div class="circle blue lighten-4 blue-text text-darken-3 stat-icon-box">
                    <i class="material-icons">cloud_upload</i>
                  </div>
                  <div class="ml-4">
                    <h3 class="grey-text text-darken-1 stat-title">Total Tips Received</h3>
                    <p class="teal-text text-darken-2 stat-value">₦{{ formatMoney(totalTipsReceived) }}</p>
                  </div>
                </div>
                <a @click="refreshBalance"
                  class="btn-flat waves-effect blue-text text-darken-1"
                  :class="{ 'animate-spin-m': refreshingBalance }"
                  :disabled="refreshingBalance"
                >
                  <i class="material-icons">refresh</i>
                </a>
              </div>
            </div>
          </div>
        </div>

        <div class="col s12 m4">
          <div class="card white hoverable">
            <div class="card-content">
              <div class="flex-stat-content">
                <div class="flex-items-center">
                  <div class="circle purple lighten-4 purple-text text-darken-3 stat-icon-box">
                    <i class="material-icons">timeline</i>
                  </div>
                  <div class="ml-4">
                    <h3 class="grey-text text-darken-1 stat-title">Total Transactions</h3>
                    <p class="teal-text text-darken-2 stat-value">{{ transactions.length }}</p>
                  </div>
                </div>
                <a @click="refreshBalance"
                  class="btn-flat waves-effect blue-text text-darken-1"
                  :class="{ 'animate-spin-m': refreshingBalance }"
                  :disabled="refreshingBalance"
                >
                  <i class="material-icons">refresh</i>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col s12 l8">
          <div class="row">
            <div class="col s12 m6">
              <div class="card white">
                <div class="card-content">
                  <div class="card-header">
                    <h2 class="card-title-m">Profile Information</h2>
                    <a @click="showEditForm = !showEditForm" class="btn-flat waves-effect blue-text text-darken-1">
                      {{ showEditForm ? 'Cancel' : 'Edit' }}
                    </a>
                  </div>

                  <div v-if="!showEditForm" class="section profile-info-list">
                    <div class="profile-item">
                      <i class="material-icons circle grey lighten-2 grey-text text-darken-3">person</i>
                      <div>
                        <p class="font-medium text-darken-4">{{ user.name }}</p>
                        <p class="text-small grey-text text-darken-1">Full Name</p>
                      </div>
                    </div>
                    <div class="profile-item">
                      <i class="material-icons circle grey lighten-2 grey-text text-darken-3">email</i>
                      <div>
                        <p class="font-medium text-darken-4">{{ user.email }}</p>
                        <p class="text-small grey-text text-darken-1">Email Address</p>
                      </div>
                    </div>
                    <div class="profile-item">
                      <i class="material-icons circle grey lighten-2 grey-text text-darken-3">phone</i>
                      <div>
                        <p class="font-medium text-darken-4">{{ user.phone }}</p>
                        <p class="text-small grey-text text-darken-1">Phone Number</p>
                      </div>
                    </div>

                    <div class="divider pt-4 mt-4"></div>
                    <div class="pt-4">
                      <a @click="toggle2FA" class="btn waves-effect waves-light grey lighten-2 grey-text text-darken-4">
                        {{ user.two_factor_enabled ? 'Disable' : 'Enable' }} 2FA
                      </a>
                    </div>
                  </div>

                  <div v-if="showEditForm" class="section">
                    <div class="input-field">
                      <input v-model="editForm.name" type="text" id="edit_name">
                      <label for="edit_name" :class="{ 'active': editForm.name }">Name</label>
                    </div>
                    <div class="input-field">
                      <input v-model="editForm.email" type="email" id="edit_email">
                      <label for="edit_email" :class="{ 'active': editForm.email }">Email</label>
                    </div>
                    <div class="input-field">
                      <input v-model="editForm.phone" type="text" id="edit_phone">
                      <label for="edit_phone" :class="{ 'active': editForm.phone }">Phone</label>
                    </div>
                    <div class="input-field">
                      <input v-model="editForm.password" type="password" id="edit_password" placeholder="Enter current password to confirm changes">
                      <label for="edit_password">Current Password</label>
                    </div>
                    <div class="mt-4">
                      <a @click="updateProfile" class="btn waves-effect waves-light blue darken-1 mr-3">
                        Update Profile
                      </a>
                      <a @click="showEditForm = false" class="btn-flat waves-effect grey lighten-2 grey-text text-darken-4">
                        Cancel
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col s12 m6">
              <div class="card white">
                <div class="card-content">
                  <div class="card-header">
                    <h2 class="card-title-m">Tipping Wallet</h2>
                    <a @click="showWithdrawForm = !showWithdrawForm" class="btn waves-effect waves-light teal darken-2">
                      {{ showWithdrawForm ? 'Cancel' : 'Withdraw Funds' }}
                    </a>
                  </div>

                  <div v-if="!showWithdrawForm" class="section center-align wallet-details">
                    <h3 class="green-text text-darken-2 wallet-balance-large">₦{{ formatMoney(wallet.actual_balance || 0) }}</h3>
                    <p class="grey-text text-darken-1">Available Balance</p>

                    <div class="divider mt-4 mb-4"></div>
                    
                    <p class="text-small grey-text text-darken-1 mb-2">Your Tipping Link:</p>
                    <div class="tipping-url-box">
                      <input :value="wallet.tipping_url" readonly class="url-input" />
                      <a @click="copyTippingUrl" class="btn-flat waves-effect grey lighten-3 grey-text text-darken-4">
                        Copy
                      </a>
                    </div>

                    <div class="mt-4">
                      <a @click="fetchTippingQrCode" class="btn waves-effect waves-light purple darken-1">
                        {{ hasTippingQr ? 'Regenerate QR Code' : 'Generate QR Code' }}
                      </a>
                    </div>

                    <div v-if="tippingQrCode" class="mt-4 center-align">
                      <img :src="tippingQrCode" alt="Tipping QR Code" class="responsive-img border-m" />
                      <p class="text-small grey-text text-darken-1 mt-2">Share this QR code to receive tips</p>
                    </div>
                  </div>

                  <div v-if="showWithdrawForm" class="section">
                    <div class="input-field">
                      <input v-model="withdrawForm.amount" type="number" min="1000" id="withdraw_amount">
                      <label for="withdraw_amount">Amount (₦)</label>
                      <span class="helper-text">Minimum: ₦1,000. Withdrawal fee: ₦300</span>
                    </div>
                    <div class="input-field">
                      <input v-model="withdrawForm.account_number" type="text" id="account_number">
                      <label for="account_number">Account Number</label>
                    </div>
                    <div class="input-field">
                        <select v-model="withdrawForm.bank_code" id="bank_select">
                            <option value="" disabled selected>Select Bank</option>
                            <option v-for="bank in banks" :value="bank.code" :key="bank.code">{{ bank.name }}</option>
                        </select>
                        <label>Bank</label>
                    </div>
                    <div class="mt-4">
                      <a @click="withdraw" class="btn waves-effect waves-light green darken-1 mr-3">
                        Confirm Withdrawal
                      </a>
                      <a @click="showWithdrawForm = false" class="btn-flat waves-effect grey lighten-2 grey-text text-darken-4">
                        Cancel
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col s12 l4">
          <notifications-component class="z-depth-1" />
        </div>
      </div>

      <div class="row">
        <div class="col s12">
          <div class="card white">
            <div class="card-content">
              <h2 class="card-title-m">Recent Transactions</h2>

              <div v-if="transactions.length === 0" class="center-align section">
                <i class="material-icons large grey-text text-lighten-1" style="font-size: 4rem;">description</i>
                <p class="grey-text text-darken-1">No transactions yet</p>
                <p class="text-small grey-text">Your transaction history will appear here</p>
              </div>

              <div v-else class="responsive-table">
                <table class="striped highlight">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Amount</th>
                      <th>Type</th>
                      <th>Status</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="transaction in transactions" :key="transaction.id">
                      <td>#{{ transaction.id }}</td>
                      <td>₦{{ formatMoney(transaction.amount) }}</td>
                      <td>
                        <span :class="getTransactionTypeClass(transaction.type)" class="badge-m">
                          {{ formatTransactionType(transaction.type) }}
                        </span>
                      </td>
                      <td>
                        <span :class="getStatusClass(transaction.status)" class="badge-m">
                          {{ formatStatus(transaction.status) }}
                        </span>
                      </td>
                      <td>{{ formatDate(transaction.created_at) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="twoFactorQr" id="twoFAModal" class="modal open modal-2fa">
      <div class="modal-content center-align">
        <h3 class="header">Setup 2FA</h3>
        <div class="mt-4">
          <img :src="twoFactorQr" alt="2FA QR Code" class="responsive-img" />
          <p class="text-small grey-text text-darken-1 mt-2">Secret: {{ twoFactorSecret }}</p>
          <p class="grey-text text-darken-1 mt-2">Scan this QR code with your authenticator app</p>
        </div>
        <div class="modal-footer">
          <a @click="twoFactorQr = ''" class="modal-close waves-effect waves-green btn-flat teal-text text-darken-2">Close</a>
        </div>
      </div>
    </div>

    <div v-if="toast.show" :class="toast.type === 'success' ? 'green' : 'red'" class="toast-m">
      {{ toast.message }}
    </div>
  </div>
</template>

<script>
// import axios from 'axios'; // Keep the import for axios functionality in methods

export default {
    name: 'Dashboard',
    data() {
        return {
            user: { name: 'Loading...', email: '', phone: '', two_factor_enabled: false },
            wallet: { balance: 0, tipping_url: 'https://tippaz.com/tip/link' },
            transactions: [],
            tippingQrCode: '',
            hasTippingQr: false,
            twoFactorQr: '',
            twoFactorSecret: '',
            showEditForm: false,
            showWithdrawForm: false,
            editForm: { name: '', email: '', phone: '', password: '' },
            withdrawForm: { amount: '', account_number: '', bank_code: '' },
            banks: [],
            loading: false,
            refreshingBalance: false,
            toast: {
                show: false,
                message: '',
                type: 'success'
            }
        };
    },
    computed: {
        totalTipsReceived() {
            return this.transactions
                .filter(t => t.type === 'tip' && t.status === 'completed')
                .reduce((sum, t) => sum + parseFloat(t.amount), 0);
        }
    },
    mounted() {
        // Ensure Materialize form elements (like <select>) are initialized
        M.AutoInit(); 
        this.fetchUserData();
        this.fetchBanks();
    },
    updated() {
        // Re-initialize select elements when data updates (e.g., banks loaded)
        var elems = document.querySelectorAll('select');
        M.FormSelect.init(elems);
    },
    methods: {
        // --- Materialize Specific Classes for Transactions and Statuses ---
        getTransactionTypeClass(type) {
            const classes = {
                'tip': 'green lighten-4 green-text text-darken-4',
                'withdrawal': 'red lighten-4 red-text text-darken-4',
                'deposit': 'blue lighten-4 blue-text text-darken-4'
            };
            return classes[type] || 'grey lighten-4 grey-text text-darken-4';
        },

        getStatusClass(status) {
            const classes = {
                'pending': 'amber lighten-4 amber-text text-darken-4',
                'completed': 'green lighten-4 green-text text-darken-4',
                'failed': 'red lighten-4 red-text text-darken-4',
                'cancelled': 'grey lighten-4 grey-text text-darken-4'
            };
            return classes[status] || 'grey lighten-4 grey-text text-darken-4';
        },

        // --- Other Methods (Keep all existing methods like fetchUserData, logout, formatMoney, etc. intact) ---
        async fetchUserData() {
            // Placeholder: Assume API call populates this.user, this.wallet, and this.transactions
            try {
                this.loading = true;
                const response = await axios.get('/api/user');
                this.user = response.data.user;
                this.wallet = response.data.wallet;
                this.transactions = response.data.transactions;
                this.editForm = {
                    name: this.user.name,
                    email: this.user.email,
                    phone: this.user.phone || '',
                    password: ''
                };
                await this.loadExistingTippingQr();
            } catch (error) {
                console.error('Fetch user error:', error);
                if (error.response?.status === 401) {
                    window.location.href = '/auth/login';
                } else {
                    this.showToast('Failed to load user data', 'error');
                }
            } finally {
                this.loading = false;
            }
        },

        async fetchBanks() {
            try {
                const response = await axios.get('/api/banks');
                this.banks = response.data.banks || [];
            } catch (error) {
                this.showToast('Failed to load banks', 'error');
            }
        },
        
        async refreshBalance() {
            try {
                this.refreshingBalance = true;
                const response = await axios.get('/api/wallet/refresh-balance');

                // Update wallet balance
                this.wallet.balance = response.data.balance;

                // Update transactions if returned
                if (response.data.transactions) {
                    this.transactions = response.data.transactions;
                }

                this.showToast('Dashboard data refreshed successfully', 'success');
            } catch (error) {
                this.showToast('Failed to refresh data', 'error');
            } finally {
                this.refreshingBalance = false;
            }
        },

        async loadExistingTippingQr() {
            try {
                const response = await axios.get('/api/wallet/qr-code');
                if (response.data?.exists) {
                    this.tippingQrCode = response.data.qr_code || '';
                    this.wallet.tipping_url = response.data.tipping_url || this.wallet.tipping_url;
                    this.hasTippingQr = true;
                } else {
                    this.tippingQrCode = '';
                    this.hasTippingQr = false;
                }
            } catch (error) {
                this.tippingQrCode = '';
                this.hasTippingQr = false;
            }
        },

        async fetchTippingQrCode() {
            try {
                const response = await axios.post('/api/wallet/qr-code');
                this.tippingQrCode = response.data.qr_code;
                this.wallet.tipping_url = response.data.tipping_url;
                this.hasTippingQr = true;
                this.showToast(response.data?.message || 'Tipping QR code regenerated successfully');
            } catch (error) {
                this.showToast(error.response?.data?.message || 'Failed to regenerate QR code', 'error');
            }
        },

        async updateProfile() {
            if (!this.editForm.password) {
                this.showToast('Please enter your current password to confirm changes', 'error');
                return;
            }

            try {
                await axios.post('/api/profile/update', this.editForm);
                this.showToast('Profile updated successfully');
                this.showEditForm = false;
                this.editForm.password = '';
                await this.fetchUserData();
            } catch (error) {
                this.showToast(error.response?.data?.message || 'Failed to update profile', 'error');
            }
        },

        async toggle2FA() {
            try {
                if (this.user.two_factor_enabled) {
                    await axios.post('/api/2fa/disable');
                    this.showToast('2FA disabled successfully');
                    await this.fetchUserData();
                } else {
                    const response = await axios.post('/api/2fa/enable');
                    this.twoFactorQr = response.data.qr_code;
                    this.twoFactorSecret = response.data.secret;
                    this.showToast('Scan the QR code with your authenticator app');
                }
            } catch (error) {
                this.showToast(error.response?.data?.message || 'Failed to toggle 2FA', 'error');
            }
        },

        async withdraw() {
            const amount = parseFloat(this.withdrawForm.amount);
            const fee = 300;
            const total = amount + fee;

            if (!amount || amount < 1000) {
                this.showToast('Minimum withdrawal amount is ₦1,000', 'error');
                return;
            }

            if (total > this.wallet.balance) {
                this.showToast(`Insufficient balance. You need ₦${this.formatMoney(total)} (including ₦300 fee)`, 'error');
                return;
            }

            if (!this.withdrawForm.account_number || !this.withdrawForm.bank_code) {
                this.showToast('Please fill in all withdrawal details', 'error');
                return;
            }

            try {
                await axios.post('/api/wallet/withdraw', this.withdrawForm);
                this.showToast('Withdrawal initiated successfully');
                this.showWithdrawForm = false;
                this.withdrawForm = { amount: '', account_number: '', bank_code: '' };
                await this.fetchUserData();
            } catch (error) {
                this.showToast(error.response?.data?.message || 'Withdrawal failed', 'error');
            }
        },

        async logout() {
            try {
                await axios.post('/auth/logout');
                window.location.href = '/';
            } catch (error) {
                // If logout fails, still redirect to home
                window.location.href = '/';
            }
        },

        copyTippingUrl() {
            navigator.clipboard.writeText(this.wallet.tipping_url).then(() => {
                this.showToast('Tipping URL copied to clipboard');
            }).catch(() => {
                this.showToast('Failed to copy URL', 'error');
            });
        },

        formatMoney(amount) {
            return new Intl.NumberFormat('en-NG').format(amount);
        },

        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-NG', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        formatTransactionType(type) {
            const types = {
                'tip': 'Tip Received',
                'withdrawal': 'Withdrawal',
                'deposit': 'Deposit'
            };
            return types[type] || type;
        },

        formatStatus(status) {
            const statuses = {
                'pending': 'Pending',
                'completed': 'Completed',
                'failed': 'Failed',
                'cancelled': 'Cancelled'
            };
            return statuses[status] || status;
        },

        showToast(message, type = 'success') {
            M.toast({html: message, classes: type === 'success' ? 'green darken-1' : 'red darken-1'});
        }
    }
}
</script>

<style scoped>
/* Custom Materialize-friendly classes */
.flex-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.flex-stat-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.flex-items-center {
    display: flex;
    align-items: center;
}

.stat-icon-box {
    width: 3rem;
    height: 3rem;
    line-height: 3rem;
    text-align: center;
    font-size: 1.5rem;
    margin-right: 1rem;
}

.stat-title {
    font-size: 0.8rem;
    font-weight: 500;
    margin: 0;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0;
}

.card-title-m {
    font-size: 1.25rem;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 0.5rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.profile-info-list {
    padding-top: 0.5rem;
}

.profile-item {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.profile-item i {
    width: 2.5rem;
    height: 2.5rem;
    line-height: 2.5rem;
    text-align: center;
    font-size: 1.25rem;
    margin-right: 1rem;
    border-radius: 50%;
}

.wallet-balance-large {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 0.5rem 0;
}

.tipping-url-box {
    display: flex;
    align-items: center;
}

.url-input {
    flex-grow: 1;
    margin-right: 0.5rem;
    /* Basic styling for readonly input to match card */
    padding: 0.5rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    background-color: #f9f9f9;
}

/* Transaction Badges */
.badge-m {
    padding: 0.25rem 0.5rem;
    border-radius: 1000px; /* Fully rounded pill */
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.animate-spin-m {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>