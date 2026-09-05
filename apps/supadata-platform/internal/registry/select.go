package registry

import (
	"context"
	"errors"
	"fmt"

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
