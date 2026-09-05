package httpapi

import (
	"context"
	"database/sql"
	"net/http"
	"net/http/httptest"
	"sync"
	"testing"

	sqlmock "github.com/DATA-DOG/go-sqlmock"
	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/database"
	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/registry"
)

type projectScopeResolverStub struct {
	projects map[string]project.Project
}

func (s projectScopeResolverStub) ResolveProject(_ context.Context, id string) (project.Project, error) {
	resolved, ok := s.projects[id]
	if !ok {
		return project.Project{}, project.ErrNotFound
	}
	return resolved, nil
}

type projectScopeHandler struct {
	mu      sync.Mutex
	seenIDs []string
}

func (h *projectScopeHandler) ServeHTTP(response http.ResponseWriter, request *http.Request) {
	scope, ok := project.ScopeFromContext(request.Context())
	if !ok {
		http.Error(response, "project scope missing", http.StatusInternalServerError)
		return
	}
	h.mu.Lock()
	h.seenIDs = append(h.seenIDs, scope.ID)
	h.mu.Unlock()
	response.WriteHeader(http.StatusNoContent)
}

func TestServerResolvesProjectScopePerRequest(t *testing.T) {
	resolver := projectScopeResolverStub{projects: map[string]project.Project{
		"alpha": {ID: "alpha", Name: "Alpha", Status: "ready"},
		"beta":  {ID: "beta", Name: "Beta", Status: "ready"},
	}}
	delegate := &projectScopeHandler{}
	server := NewServer(ServerOptions{
		ProjectResolver:     resolver,
		RequireProjectScope: true,
		REST:                delegate,
	})

	for _, id := range []string{"alpha", "beta", "alpha"} {
		request := httptest.NewRequest(http.MethodGet, "/rest/v1/items", nil)
		request.Header.Set("X-Supadata-Project", id)
		response := httptest.NewRecorder()
		server.Handler().ServeHTTP(response, request)
		if response.Code != http.StatusNoContent {
			t.Fatalf("project %q response status = %d, want %d", id, response.Code, http.StatusNoContent)
		}
	}

	want := []string{"alpha", "beta", "alpha"}
	if len(delegate.seenIDs) != len(want) {
		t.Fatalf("seen project count = %d, want %d", len(delegate.seenIDs), len(want))
	}
	for index := range want {
		if delegate.seenIDs[index] != want[index] {
			t.Fatalf("seen project %d = %q, want %q", index, delegate.seenIDs[index], want[index])
		}
	}
}

func TestServerResolvesProjectScopeFromProjectHostname(t *testing.T) {
	store, err := registry.New(registry.Options{DataDir: t.TempDir(), PublicHost: "supabase.example.com"})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}
	if _, err := store.CreateProject(context.Background(), "Alpha", "alpha"); err != nil {
		t.Fatalf("CreateProject() error = %v", err)
	}
	delegate := &projectScopeHandler{}
	server := NewServer(ServerOptions{
		ProjectResolver:     store,
		RequireProjectScope: true,
		REST:                delegate,
	})
	request := httptest.NewRequest(http.MethodGet, "https://alpha.supabase.example.com/rest/v1/items", nil)
	response := httptest.NewRecorder()

	server.Handler().ServeHTTP(response, request)

	if response.Code != http.StatusNoContent {
		t.Fatalf("response status = %d, want %d", response.Code, http.StatusNoContent)
	}
	if len(delegate.seenIDs) != 1 || delegate.seenIDs[0] != "alpha" {
		t.Fatalf("seen project IDs = %v, want [alpha]", delegate.seenIDs)
	}
}

type projectDatabaseHandler struct {
	want *sql.DB
}

func (h projectDatabaseHandler) ServeHTTP(response http.ResponseWriter, request *http.Request) {
	got, ok := database.ConnectionFromContext(request.Context())
	if !ok || got != h.want {
		http.Error(response, "wrong project database", http.StatusInternalServerError)
		return
	}
	response.WriteHeader(http.StatusNoContent)
}

func TestServerBindsResolvedProjectDatabase(t *testing.T) {
	alphaDB, _, err := sqlmock.New()
	if err != nil {
		t.Fatalf("sqlmock.New() error = %v", err)
	}
	defer alphaDB.Close()
	router := database.NewRouter(nil)
	if err := router.Register("alpha", alphaDB); err != nil {
		t.Fatalf("Register(alpha) error = %v", err)
	}
	server := NewServer(ServerOptions{
		ProjectResolver:     projectScopeResolverStub{projects: map[string]project.Project{"alpha": {ID: "alpha"}}},
		DatabaseResolver:    router,
		RequireProjectScope: true,
		REST:                projectDatabaseHandler{want: alphaDB},
	})
	request := httptest.NewRequest(http.MethodGet, "/rest/v1/items", nil)
	request.Header.Set("X-Supadata-Project", "alpha")
	response := httptest.NewRecorder()

	server.Handler().ServeHTTP(response, request)

	if response.Code != http.StatusNoContent {
		t.Fatalf("response status = %d, want %d", response.Code, http.StatusNoContent)
	}
}

func TestServerRejectsUnknownProjectScope(t *testing.T) {
	server := NewServer(ServerOptions{
		ProjectResolver:     projectScopeResolverStub{projects: map[string]project.Project{}},
		RequireProjectScope: true,
		REST:                &projectScopeHandler{},
	})
	request := httptest.NewRequest(http.MethodGet, "/rest/v1/items", nil)
	request.Header.Set("X-Supadata-Project", "missing")
	response := httptest.NewRecorder()

	server.Handler().ServeHTTP(response, request)

	if response.Code != http.StatusNotFound {
		t.Fatalf("response status = %d, want %d", response.Code, http.StatusNotFound)
	}
}

func TestServerRejectsMissingProjectScopeWhenRequired(t *testing.T) {
	server := NewServer(ServerOptions{
		ProjectResolver:     projectScopeResolverStub{projects: map[string]project.Project{}},
		RequireProjectScope: true,
		REST:                &projectScopeHandler{},
	})
	request := httptest.NewRequest(http.MethodGet, "/rest/v1/items", nil)
	response := httptest.NewRecorder()

	server.Handler().ServeHTTP(response, request)

	if response.Code != http.StatusBadRequest {
		t.Fatalf("response status = %d, want %d", response.Code, http.StatusBadRequest)
	}
}
