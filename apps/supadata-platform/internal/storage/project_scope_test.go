package storage

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

func TestStorageRejectsCrossProjectBucket(t *testing.T) {
	store := newMemoryStore()
	if _, err := store.Put(context.Background(), "supadata-beta", "private.txt", "text/plain", strings.NewReader("beta"), -1); err != nil {
		t.Fatalf("seed beta object: %v", err)
	}
	handler := NewHandler(HandlerOptions{Store: store, APIKeys: APIKeyConfig{Anon: "anon"}})
	request := httptest.NewRequest(http.MethodGet, "/storage/v1/object/supadata-beta/private.txt", nil)
	request.Header.Set("apikey", "anon")
	request = request.WithContext(project.WithScope(request.Context(), project.Project{
		ID:    "alpha",
		Scope: project.ResourceScope{Storage: project.StorageScope{Bucket: "supadata-alpha"}},
	}))
	response := httptest.NewRecorder()

	handler.ServeHTTP(response, request)

	if response.Code != http.StatusNotFound {
		t.Fatalf("status = %d, want %d", response.Code, http.StatusNotFound)
	}
}
