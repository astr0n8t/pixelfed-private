<template>
  <div class="web-wrapper">
	<div class="container-fluid mt-3">
	<div class="invites-container">
    <h2 class="invites-title">Create Invite</h2>
    <form @submit.prevent="submitForm">
      <div class="form-group">
        <label for="email">Email</label>
        <input
          type="email"
          id="email"
          v-model="form.email"
          class="form-control"
          required
        >
        <div v-if="errors.email" class="text-danger">{{ errors.email[0] }}</div>
      </div>
      <div class="form-group">
        <label for="message">Message</label>
        <textarea
          id="message"
          v-model="form.message"
          class="form-control"
        ></textarea>
        <div v-if="errors.message" class="text-danger">{{ errors.message[0] }}</div>
      </div>
      <button type="submit" class="btn btn-primary">Send Invite</button>
    </form>
    <div v-if="errors.general" class="alert alert-danger mt-3">
      {{ errors.general[0] }}
    </div>
    </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  data() {
    return {
      form: {
        email: '',
        message: ''
      },
      errors: {}
    };
  },
  methods: {
    submitForm() {
      axios
        .post('/api/invites/create', this.form)
        .then(() => {
          this.$router.push('/i/invites');
        })
        .catch(error => {
          if (error.response && error.response.status === 422) {
            this.errors = error.response.data.errors;
          } else {
            console.error(error);
            this.errors = { general: ['Failed to create invite'] };
          }
        });
    }
  }
};
</script>

<style scoped>
.invites-container {
  background-color: var(--container-bg);
  padding: 20px;
  border-radius: 8px;
  box-shadow: var(--shadow-color);
}

.invites-title {
  color: var(--text-color);
  font-size: 1.5rem;
  margin-bottom: 1rem;
}

.form-group {
  margin-bottom: 1.5rem;
}

.formබform-group label {
  color: var(--text-color);
  font-weight: 500;
}

.form-control {
  border-color: var(--table-header-bg);
  color: var(--text-color);
}

.form-control:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 0.2rem var(--primary-color-shadow, rgba(74, 144, 226, 0.25));
}

.text-danger {
  color: var(--error-color);
  font-size: 0.875rem;
  margin-top: 0.25rem;
}
</style>

