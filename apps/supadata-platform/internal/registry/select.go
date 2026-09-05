package registry

import (
	"context"
	"errors"
	"fmt"
	"net"
	"strings"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

func (s *Store) ResolveProject(_ context.Context, id string) (project.Project, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	current, err := s.readLocked()
	if err != nil {
		return project.Project{}, err
	}
	for _, candidate := range current.Projects {
		if candidate.ID == id {
			return candidate, nil
		}
	}
	return project.Project{}, errors.Join(project.ErrNotFound, fmt.Errorf("project '%s' not found", id))
}

func (s *Store) ResolveProjectHost(ctx context.Context, rawHost string) (project.Project, error) {
	host := strings.ToLower(strings.TrimSpace(rawHost))
	if parsedHost, _, err := net.SplitHostPort(host); err == nil {
		host = parsedHost
	}
	publicHost := strings.ToLower(strings.TrimSpace(strings.TrimPrefix(strings.TrimPrefix(s.publicHost, "https://"), "http://")))
	if publicHost == "" || host == publicHost {
		return project.Project{}, project.ErrNoProjectHost
	}
	prefix, ok := strings.CutSuffix(host, "."+publicHost)
	if !ok || prefix == "" || strings.Contains(prefix, ".") {
		return project.Project{}, project.ErrNoProjectHost
	}
	return s.ResolveProject(ctx, prefix)
}

func (s *Store) SelectProject(_ context.Context, id string) (project.Project, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	current, err := s.readLocked()
	if err != nil {
		return project.Project{}, err
	}
	found := -1
	for index := range current.Projects {
		if current.Projects[index].ID == id {
			found = index
			break
		}
	}
	if found < 0 {
		return project.Project{}, fmt.Errorf("project '%s' not found", id)
	}
	current.CurrentProjectID = id
	for index := range current.Projects {
		current.Projects[index].Current = index == found
	}
	if err := s.writeLocked(current); err != nil {
		return project.Project{}, err
	}
	return current.Projects[found], nil
}
