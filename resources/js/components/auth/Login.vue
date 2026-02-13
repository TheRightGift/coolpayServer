<template>
  <div class="row">
    <div class="col s12 m8 l6 offset-m2 offset-l3 z-depth-2 card grey lighten-5" style="margin-top: 5rem; padding: 2rem;">
      <div class="center-align">
        <h2 class="teal-text text-darken-2">
          Sign in to your account
        </h2>
      </div>

      <form class="col s12" @submit.prevent="login">
        
        <div class="row">
          <div class="input-field col s12">
            <i class="material-icons prefix">email</i>
            <input
              id="email"
              v-model="form.email"
              name="email"
              type="email"
              autocomplete="email"
              required
              class="validate"
              placeholder=""
            >
            <label for="email">Email address</label>
          </div>
        </div>
        
        <div v-if="!requires2FA" class="row">
          <div class="input-field col s12">
            <i class="material-icons prefix">lock</i>
            <input
              id="password"
              v-model="form.password"
              name="password"
              type="password"
              autocomplete="current-password"
              required
              class="validate"
              placeholder=""
            >
            <label for="password">Password</label>
          </div>
        </div>

        <div v-if="requires2FA" class="row">
          <div class="input-field col s12">
            <i class="material-icons prefix">security</i>
            <input
              id="two_factor_code"
              v-model="twoFactorCode"
              name="two_factor_code"
              type="text"
              maxlength="8"
              required
              class="validate"
              placeholder=""
            >
            <label for="two_factor_code">Authenticator Code</label>
          </div>
        </div>

        <div v-if="errors.length > 0" class="card-panel red lighten-4 red-text text-darken-4">
            <h3 class="flow-text" style="font-size: 1.2rem; margin-top: 0;">
              There were errors with your submission
            </h3>
            <ul class="browser-default pl-4">
              <li v-for="error in errors" :key="error">{{ error }}</li>
            </ul>
        </div>

        <div v-if="success" class="card-panel green lighten-4 green-text text-darken-4">
            <p>{{ success }}</p>
        </div>

        <div class="row">
          <div class="col s12">
            <button
              type="submit"
              class="btn-large waves-effect waves-light teal darken-2 full-width"
              :disabled="loading"
            >
              <span v-if="!loading">{{ requires2FA ? 'Verify 2FA' : 'Sign in' }}</span>
              <span v-else>{{ requires2FA ? 'Verifying...' : 'Signing in...' }}</span>
            </button>
          </div>
        </div>

        <div class="row center-align">
          <p class="grey-text text-darken-1">
            Test credentials: <strong>user.a@test.com</strong> / <strong>password123</strong>
          </p>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
/**
 * Helper function to store the Passport token data
 */
function storeAuthToken(tokenData) {
    if (tokenData && tokenData.access_token) {
        localStorage.setItem('auth_token', tokenData.access_token);
        // Set the Authorization header for immediate use
        axios.defaults.headers.common['Authorization'] = `Bearer ${tokenData.access_token}`;
    }
}

export default {
  name: 'LoginComponent',
  data() {
    return {
      form: {
        email: '',
        password: ''
      },
      errors: [],
      success: '',
      loading: false,
      requires2FA: false,
      challengeToken: '',
      twoFactorCode: ''
    }
  },
  methods: {
    async login() {
      this.loading = true;
      this.errors = [];
      this.success = '';

      try {
        let response;

        if (!this.requires2FA) {
          response = await axios.post('/auth/login', this.form);

          if (response.data?.requires_2fa) {
            this.requires2FA = true;
            this.challengeToken = response.data.challenge_token;
            this.twoFactorCode = '';
            this.success = 'Enter the code from your authenticator app to continue.';
            return;
          }
        } else {
          response = await axios.post('/api/auth/2fa/verify-login', {
            challenge_token: this.challengeToken,
            code: this.twoFactorCode,
          });
        }

        if (response.data && response.data.token_data) {
          storeAuthToken(response.data.token_data);
          this.success = 'Login successful! Redirecting to dashboard...';
          setTimeout(() => {
            window.location.href = '/dashboard';
          }, 1000);
        } else {
          this.errors = ['Login failed. Invalid response structure from server.'];
        }
      } catch (error) {
        if (error.response) {
          if (error.response.status === 401) {
            this.errors = ['Invalid credentials. Please check your email and password.'];
          } else if (error.response.data && error.response.data.errors) {
            this.errors = Object.values(error.response.data.errors).flat();
          } else if (error.response.data && error.response.data.message) {
            this.errors = [error.response.data.message];
          } else {
            this.errors = [`An error occurred (Status ${error.response.status}).`];
          }
        } else {
          this.errors = ['Network error. Please try again.'];
        }
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>

<style scoped>
/* Custom style for the Materialize button to span the full width */
.full-width {
  width: 100%;
}
/* Basic list styling for error messages */
.browser-default {
    list-style-type: disc;
    margin-left: 1rem;
}
.pl-4 {
    padding-left: 1rem;
}
</style>