package realtime

import (
	"context"
	"net/http"
	"net/http/httptest"
	"net/url"
	"testing"
	"time"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/jwt"
	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

func TestProjectTopicKeySeparatesProjectsWithSameTopic(t *testing.T) {
	alpha := project.WithScope(context.Background(), project.Project{ID: "alpha"})
	beta := project.WithScope(context.Background(), project.Project{ID: "beta"})

	alphaKey := ProjectTopicKey(alpha, "realtime:public:events")
	betaKey := ProjectTopicKey(beta, "realtime:public:events")
	if alphaKey == betaKey {
		t.Fatalf("project topic keys collide: %q", alphaKey)
	}
	if ProjectTopicKey(context.Background(), "realtime:public:events") != "realtime:public:events" {
		t.Fatal("unscoped topic key changed")
	}
}

func TestRealtimeProjectTokenCannotCrossProjectBoundary(t *testing.T) {
	secret := []byte("realtime-secret")
	token, err := jwt.SignHS256(jwt.Claims{Subject: "user-id", Role: "authenticated", ProjectID: "alpha", ExpiresAt: time.Now().Add(time.Hour).Unix()}, secret)
	if err != nil {
		t.Fatal(err)
	}
	handler := NewHandler(HandlerOptions{JWTSecret: secret})
	request := httptest.NewRequest(http.MethodGet, "/realtime/v1/websocket?access_token="+url.QueryEscape(token), nil)
	request = request.WithContext(project.WithScope(request.Context(), project.Project{ID: "beta"}))
	if err := handler.validateAccessToken(request); err == nil {
		t.Fatal("alpha token was accepted for beta realtime connection")
	}
}
