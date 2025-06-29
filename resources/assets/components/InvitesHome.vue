<template>
  <div class="web-wrapper">
	<div class="container-fluid mt-3">
	<div class="invites-container">
    <h2 class="invites-title">My Invites</h2>
    <div v-if="loading" class="text-center">
      <div class="spinner-border" role="status">
        <span class="sr-only">Loading...</span>
      </div>
    </div>
    <div v-else-if="invites.length">
      <table class="table table-striped invites-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Message</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="invite in invites" :key="invite.id">
            <td>{{ invite.id }}</td>
            <td>{{ invite.email }}</td>
            <td>{{ invite.message || 'No message' }}</td>
            <td>
              <button class="btn btn-danger btn-sm" @click="deleteInvite(invite.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <!-- Pagination Controls -->
      <nav v-if="pagination.total > pagination.per_page">
        <ul class="pagination">
          <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
            <a class="page-link" @click.prevent="fetchInvites(pagination.current_page - 1)">Previous</a>
          </li>
          <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
            <a class="page-link" @click.prevent="fetchInvites(pagination.current_page + 1)">Next</a>
          </li>
        </ul>
      </nav>
    </div>
    <div v-else class="alert alert-info">
      No invites found.
    </div>
    <router-link to="/i/invites/create" class="btn btn-primary mt-3">Invite Someone</router-link>
    <div v-if="errors.length" class="alert alert-danger mt-3">
      <ul>
        <li v-for="error in errors" :key="error">{{ error }}</li>
      </ul>
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
      invites: [],
      pagination: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0
      },
      errors: [],
      loading: false
    };
  },
  mounted() {
    this.fetchInvites();
  },
  methods: {
    fetchInvites(page = 1) {
      this.loading = true;
      axios
        .get('/api/invites/get', {
          headers: { Accept: 'application/json' },
          params: { page }
        })
        .then(response => {
          this.invites = response.data.data || response.data;
          this.pagination = {
            current_page: response.data.current_page || 1,
            last_page: response.data.last_page || 1,
            per_page: response.data.per_page || 10,
            total: response.data.total || response.data.length
          };
        })
        .catch(error => {
          console.error('Fetch Error:', error);
          this.errors = ['Failed to fetch invites'];
        })
        .finally(() => {
          this.loading = false;
        });
    },
    deleteInvite(id) {
      this.loading = true;
      axios
        .post('/api/invites/delete', { id })
        .then(() => {
        this.fetchInvites(this.pagination.current_page);
        })
        .catch(error => {
        this.errors = ['Failed to delete invite'];
        })
        .finally(() => {
        this.loading = false;
        });
    }
  }
};
</script>

<style scoped>
.invites-container {
  background-color: #F8FAFC;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.invites-title {
  color: #1A202C;
  font-size: 1.5rem;
  margin-bottom: 1rem;
}

.invites-table {
  background-color: #FFFFFF;
}

.invites-table th {
  background-color: #E2E8F0;
  color: #1A202C;
  font-weight: 600;
  padding: 12px;
}

.invites-table td {
  color: #1A202C;
  padding: 12px;
}

.invites-table tbody tr:hover {
  background-color: #EDF2F7;
}

.btn-primary {
  background-color: #4A90E2;
  border-color: #4A90E2;
}

.btn-primary:hover {
  background-color: #357ABD;
  border-color: #357ABD;
}

.btn-danger {
  background-color: #D32F2F;
  border-color: #D32F2F;
}

.btn-danger:hover {
  background-color: #B71C1C;
  border-color: #B71C1C;
}

.alert-danger {
  background-color: #FFEBEE;
  color: #B00020;
  border-color: #FFCDD2;
}
</style>
